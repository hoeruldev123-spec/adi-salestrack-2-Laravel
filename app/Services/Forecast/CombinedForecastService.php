<?php

namespace App\Services\Forecast;

use App\Models\ForecastRun;

class CombinedForecastService
{
    public function __construct(
        private readonly MonteCarloService $monteCarlo,
        private readonly HistoricalForecastService $historical,
        private readonly OpportunityForecastService $opportunity,
    ) {}

    /**
     * @return array<int, float>
     */
    public function simulate(ForecastRun $run, ?int $seed = null): array
    {
        $monthly = $this->historical->monthlyRevenue($run);
        $opportunities = $this->opportunity->opportunities($run);

        return $this->monteCarlo->simulateCombined(
            $opportunities,
            $monthly,
            $run->simulation_count,
            10,
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
