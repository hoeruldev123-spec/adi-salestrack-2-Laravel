<?php

namespace App\Services\Forecast;

use App\Models\ForecastRun;
use App\Models\ForecastSimulation;
use Illuminate\Support\Collection;

class ForecastResultService
{
    public function __construct(
        private readonly OpportunityForecastService $opportunities,
        private readonly MonteCarloService $monteCarlo,
    ) {}

    /**
     * Prediction result summary for the result page.
     *
     * @return array<string, mixed>|null
     */
    public function summary(ForecastRun $run): ?array
    {
        $result = $run->result()->first();

        if (! $result) {
            return null;
        }

        $report = $this->opportunities->probabilityReport($run);
        $opportunities = $this->opportunities->opportunities($run, $report);
        $totalValue = array_sum(array_column($opportunities, 'value'));
        $expectedOpportunityValue = array_sum(array_map(
            fn (array $opportunity) => (float) $opportunity['value'] * (float) $opportunity['probability'],
            $opportunities
        ));

        return [
            'id' => $result->id,
            'forecast_run_id' => $run->id,
            'forecast_name' => $run->forecast_name ?: $run->name,
            'forecast_period' => [
                'start' => $run->forecastStart()->toDateString(),
                'end' => $run->forecastEnd()->toDateString(),
            ],
            'historical_period' => $report['period'],
            'simulation_count' => (int) $run->simulation_count,
            'total_opportunities' => count($opportunities),
            'total_opportunity_value' => round((float) $totalValue, 2),
            'expected_opportunity_value' => round((float) $expectedOpportunityValue, 2),
            'minimum' => (float) $result->minimum,
            'maximum' => (float) $result->maximum,
            'mean' => (float) $result->mean,
            'median' => (float) $result->median,
            'p10' => (float) $result->p10,
            'p25' => (float) $result->p25,
            'p50' => (float) $result->p50,
            'p75' => (float) $result->p75,
            'p90' => (float) $result->p90,
            'p95' => (float) $result->p95,
            'target_probability' => $result->target_probability !== null
                ? (float) $result->target_probability
                : null,
            'expected_revenue' => (float) ($run->predicted_revenue ?? $result->mean),
            'best_case' => (float) ($run->best_case ?? $result->p90),
            'worst_case' => (float) ($run->worst_case ?? $result->p10),
            'most_likely' => (float) ($run->most_likely ?? $result->p50),
            'actual_revenue' => $run->actual_revenue !== null ? (float) $run->actual_revenue : null,
            'mape' => $run->mape !== null ? (float) $run->mape : null,
            'scenarios' => [
                'worst_case' => (float) ($run->worst_case ?? $result->p10),
                'most_likely' => (float) ($run->most_likely ?? $result->p50),
                'best_case' => (float) ($run->best_case ?? $result->p90),
                'expected_revenue' => (float) ($run->predicted_revenue ?? $result->mean),
            ],
            'warnings' => $run->warnings ?? $report['warnings'],
            'opportunities' => $opportunities,
        ];
    }

    /**
     * Histogram and percentile lines for the simulation distribution.
     *
     * @return array<string, mixed>
     */
    public function distribution(ForecastRun $run): array
    {
        $simulations = $run->simulations()
            ->orderBy('simulation_number')
            ->pluck('total_revenue')
            ->map(fn ($value) => (float) $value)
            ->all();

        $bins = (int) config('salestrack.forecast.distribution_bins', 20);

        if (empty($simulations)) {
            $result = $run->result()->first();

            return [
                'forecast_run_id' => $run->id,
                'iteration_count' => 0,
                'histogram' => [],
                'bins' => [],
                'p10' => $result ? (float) $result->p10 : null,
                'p50' => $result ? (float) $result->p50 : null,
                'p90' => $result ? (float) $result->p90 : null,
                'mean' => $result ? (float) $result->mean : null,
                'minimum' => $result ? (float) $result->minimum : null,
                'maximum' => $result ? (float) $result->maximum : null,
                'series' => [],
            ];
        }

        $statistics = $this->monteCarlo->calculateStatistics($simulations);
        $histogram = $this->monteCarlo->histogram($simulations, $bins);

        return [
            'forecast_run_id' => $run->id,
            'iteration_count' => count($simulations),
            'histogram' => $histogram,
            'bins' => $histogram,
            'p10' => $statistics['p10'],
            'p25' => $statistics['p25'],
            'p50' => $statistics['p50'],
            'p75' => $statistics['p75'],
            'p90' => $statistics['p90'],
            'p95' => $statistics['p95'],
            'mean' => $statistics['mean'],
            'median' => $statistics['median'],
            'minimum' => $statistics['minimum'],
            'maximum' => $statistics['maximum'],
            'series' => array_slice($simulations, 0, 1000),
        ];
    }

    /**
     * Revenue contribution grouped by principal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byPrincipal(ForecastRun $run): array
    {
        return $this->groupBreakdown(
            $this->opportunities->opportunities($run),
            fn (array $opportunity) => [
                'key' => (string) ($opportunity['principal_id'] ?? 'UNASSIGNED'),
                'label' => $opportunity['principal_name'] ?: 'Unassigned',
                'amount' => (float) $opportunity['value'],
            ],
            'principal'
        );
    }

    /**
     * Revenue contribution grouped by product category.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byCategory(ForecastRun $run): array
    {
        $opportunities = $this->opportunities->opportunities($run);
        $groups = $this->emptyCategoryGroups();

        foreach ($opportunities as $opportunity) {
            $probability = (float) $opportunity['probability'];

            foreach ($opportunity['categories'] as $category => $amount) {
                $key = in_array($category, ['LICENSE', 'HARDWARE', 'CLOUD', 'SERVICE'], true)
                    ? $category
                    : 'OTHER';

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'category' => $key,
                        'opportunity_count' => 0,
                        'estimated_value' => 0.0,
                        'expected_revenue' => 0.0,
                    ];
                }

                $groups[$key]['opportunity_count']++;
                $groups[$key]['estimated_value'] += (float) $amount;
                $groups[$key]['expected_revenue'] += (float) $amount * $probability;
            }
        }

        return array_values(array_map(function (array $group) {
            $group['estimated_value'] = round($group['estimated_value'], 2);
            $group['expected_revenue'] = round($group['expected_revenue'], 2);

            return $group;
        }, $groups));
    }

    /**
     * @param  array<int, array<string, mixed>>  $opportunities
     * @return array<int, array<string, mixed>>
     */
    private function groupBreakdown(array $opportunities, callable $resolver, string $labelKey): array
    {
        $groups = [];

        foreach ($opportunities as $opportunity) {
            $resolved = $resolver($opportunity);
            $key = $resolved['key'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    $labelKey . '_id' => $key === 'UNASSIGNED' ? null : (int) $key,
                    $labelKey => $resolved['label'],
                    'opportunity_count' => 0,
                    'estimated_value' => 0.0,
                    'expected_revenue' => 0.0,
                ];
            }

            $groups[$key]['opportunity_count']++;
            $groups[$key]['estimated_value'] += (float) $resolved['amount'];
            $groups[$key]['expected_revenue'] += (float) $resolved['amount'] * (float) $opportunity['probability'];
        }

        $groups = array_map(function (array $group) {
            $group['estimated_value'] = round($group['estimated_value'], 2);
            $group['expected_revenue'] = round($group['expected_revenue'], 2);

            return $group;
        }, $groups);

        usort($groups, fn (array $left, array $right) => $right['expected_revenue'] <=> $left['expected_revenue']);

        return array_values($groups);
    }

    /**
     * @return array<string, array{category: string, opportunity_count: int, estimated_value: float, expected_revenue: float}>
     */
    private function emptyCategoryGroups(): array
    {
        $groups = [];

        foreach (['LICENSE', 'HARDWARE', 'CLOUD', 'SERVICE'] as $category) {
            $groups[$category] = [
                'category' => $category,
                'opportunity_count' => 0,
                'estimated_value' => 0.0,
                'expected_revenue' => 0.0,
            ];
        }

        return $groups;
    }
}
