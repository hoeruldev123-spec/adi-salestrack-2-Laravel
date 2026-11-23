<?php

namespace App\Services\Forecast;

use App\Enums\OpportunityStatus;
use App\Models\ForecastRun;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use Carbon\Carbon;

class OpportunityForecastService
{
    public function __construct(
        private readonly MonteCarloService $monteCarlo,
        private readonly StageProbabilityService $stageProbabilities,
    ) {}

    /**
     * Probability distribution used by a forecast run.
     */
    public function probabilityReport(ForecastRun $run): array
    {
        return $this->stageProbabilities->forRun($run);
    }

    /**
     * Open opportunities that fall within the forecast period and match the
     * run's principal/category scope.
     *
     * @return array<int, array<string, mixed>>
     */
    public function opportunities(ForecastRun $run, ?array $probabilityReport = null): array
    {
        $report = $probabilityReport ?? $this->probabilityReport($run);
        $start = $run->forecastStart()->toDateString();
        $end = $run->forecastEnd()->toDateString();
        $category = strtoupper((string) ($run->revenue_category ?: 'ALL'));

        $query = Opportunity::query()
            ->with([
                'principal:id,name',
                'items.product:id,name,product_type,type,category_id',
                'activities' => fn ($query) => $query->orderByDesc('activity_date')->orderByDesc('id'),
            ])
            ->where('status', OpportunityStatus::OPEN->value)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('closing_date', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->whereNull('closing_date')
                            ->whereBetween('expected_close_date', [$start, $end]);
                    });
            })
            ->when($run->principal_id, fn ($query, $principalId) => $query->where('principal_id', $principalId))
            ->orderBy('id');

        return $query->get()
            ->map(function (Opportunity $opportunity) use ($report, $category) {
                $breakdown = $this->valueBreakdown($opportunity, $category);

                if ($breakdown['value'] <= 0) {
                    return null;
                }

                $stage = strtoupper(trim((string) ($opportunity->stage ?: $opportunity->progress ?: 'LEAD')));
                $stageProbability = $report['stages'][$stage] ?? null;
                $probability = $stageProbability['probability']
                    ?? $this->fallbackProbability($opportunity);

                $lastActivity = $opportunity->activities->first();
                $closingDate = $opportunity->closing_date ?: $opportunity->expected_close_date;

                return [
                    'id' => $opportunity->id,
                    'title' => $opportunity->title ?: $opportunity->project_name,
                    'project_name' => $opportunity->project_name ?: $opportunity->title,
                    'client_name' => $opportunity->client_name ?: $opportunity->company?->name,
                    'stage' => $stage,
                    'status' => $opportunity->status,
                    'principal_id' => $opportunity->principal_id,
                    'principal_name' => $opportunity->principal?->name,
                    'value' => round($breakdown['value'], 2),
                    'probability' => round((float) $probability, 6),
                    'probability_percent' => round((float) $probability * 100, 2),
                    'probability_source' => $stageProbability['source'] ?? 'OPPORTUNITY',
                    'closing_date' => $closingDate?->toDateString(),
                    'last_activity_at' => $lastActivity?->activity_date?->toDateTimeString()
                        ?? $lastActivity?->created_at?->toDateTimeString(),
                    'categories' => $breakdown['categories'],
                    'item_count' => $opportunity->items->count(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, float>
     */
    public function simulate(ForecastRun $run, ?int $seed = null): array
    {
        return $this->monteCarlo->simulateOpportunities(
            $this->opportunities($run),
            $run->simulation_count,
            $seed
        );
    }

    /**
     * Simulate and return the opportunity metadata alongside the totals.
     *
     * @return array{totals: array<int, float>, opportunities: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    public function simulateWithDetails(ForecastRun $run, ?int $seed = null, ?callable $onIteration = null): array
    {
        $report = $this->probabilityReport($run);
        $opportunities = $this->opportunities($run, $report);

        $simulation = $this->monteCarlo->simulateDetailed(
            $opportunities,
            $run->simulation_count,
            $seed,
            $onIteration
        );

        $warnings = $report['warnings'];

        if (empty($opportunities)) {
            $warnings[] = 'Tidak ada opportunity OPEN pada periode forecast yang sesuai dengan filter.';
        }

        return [
            'totals' => $simulation['totals'],
            'opportunities' => $opportunities,
            'warnings' => $warnings,
        ];
    }

    public function statistics(ForecastRun $run, ?int $seed = null): array
    {
        $results = $this->simulate($run, $seed);

        return $this->monteCarlo->calculateStatistics(
            $results,
            $run->target_revenue !== null ? (float) $run->target_revenue : null
        );
    }

    /**
     * Split an opportunity value by product category.
     *
     * @return array{value: float, categories: array<string, float>}
     */
    private function valueBreakdown(Opportunity $opportunity, string $categoryFilter): array
    {
        /** @var \Illuminate\Support\Collection<int, OpportunityItem> $items */
        $items = $opportunity->items;

        if ($items->isEmpty()) {
            if ($categoryFilter !== 'ALL') {
                return ['value' => 0.0, 'categories' => []];
            }

            $value = (float) ($opportunity->estimated_value ?? $opportunity->value ?? 0);

            return ['value' => $value, 'categories' => $value > 0 ? ['OTHER' => $value] : []];
        }

        $categories = [];

        foreach ($items as $item) {
            $itemCategory = strtoupper((string) ($item->product?->product_type ?: $item->product?->type ?: 'OTHER'));
            $amount = (float) $item->subtotal;

            if ($amount <= 0) {
                $amount = (float) $item->quantity * (float) $item->unit_price;
            }

            if ($categoryFilter !== 'ALL' && $itemCategory !== $categoryFilter) {
                continue;
            }

            $categories[$itemCategory] = ($categories[$itemCategory] ?? 0) + $amount;
        }

        return [
            'value' => round(array_sum($categories), 2),
            'categories' => array_map(fn ($value) => round((float) $value, 2), $categories),
        ];
    }

    private function fallbackProbability(Opportunity $opportunity): float
    {
        $probability = (float) ($opportunity->probability ?? 0) / 100;

        if ($probability > 0) {
            return min(1.0, $probability);
        }

        $stage = strtoupper(trim((string) ($opportunity->stage ?: $opportunity->progress ?: 'LEAD')));
        $fallback = \App\Enums\OpportunityStage::tryFrom($stage)?->defaultProbability() ?? 10;

        return $fallback / 100;
    }
}
