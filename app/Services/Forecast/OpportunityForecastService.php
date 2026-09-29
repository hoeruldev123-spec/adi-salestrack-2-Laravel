<?php

namespace App\Services\Forecast;

use App\Enums\OpportunityStatus;
use App\Models\ForecastRun;
use App\Models\Opportunity;

class OpportunityForecastService
{
    public function __construct(private readonly MonteCarloService $monteCarlo) {}

    /**
     * Open opportunities within the forecast period.
     *
     * @return array<int, array{value: float, probability: float}>
     */
    public function opportunities(ForecastRun $run): array
    {
        return Opportunity::query()
            ->where('status', OpportunityStatus::OPEN->value)
            ->whereBetween('expected_close_date', [$run->period_start, $run->period_end])
            ->get(['id', 'title', 'value', 'probability'])
            ->map(fn ($opportunity) => [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'value' => (float) $opportunity->value,
                'probability' => (float) ($opportunity->probability / 100),
            ])
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

    public function statistics(ForecastRun $run, ?int $seed = null): array
    {
        $results = $this->simulate($run, $seed);

        return $this->monteCarlo->calculateStatistics(
            $results,
            $run->target_revenue !== null ? (float) $run->target_revenue : null
        );
    }
}
