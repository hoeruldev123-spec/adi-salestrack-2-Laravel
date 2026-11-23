<?php

namespace App\Services\Forecast;

use App\Models\ForecastRun;
use App\Models\ForecastScenario;

class ScenarioForecastService
{
    public function __construct(private readonly MonteCarloService $monteCarlo) {}

    /**
     * @return array<int, float>
     */
    public function simulate(ForecastRun $run, ForecastScenario $scenario, ?int $seed = null): array
    {
        $opportunities = app(OpportunityForecastService::class)->opportunities($run);

        $winRateAdjustment = (float) ($scenario->win_rate_adjustment ?? 0);
        $revenueAdjustment = (float) ($scenario->revenue_adjustment ?? 0);
        $opportunityAdjustment = (float) ($scenario->opportunity_adjustment ?? 0);

        $adjusted = [];
        foreach ($opportunities as $opportunity) {
            $probability = $opportunity['probability'];

            if ($winRateAdjustment > 0) {
                $probability = min(1.0, $probability + $winRateAdjustment / 100);
            } elseif ($winRateAdjustment < 0) {
                $probability = max(0.0, $probability + $winRateAdjustment / 100);
            }

            $value = $opportunity['value'];
            if ($opportunityAdjustment != 0.0) {
                $value = max(0.0, $value * (1 + $opportunityAdjustment / 100));
            }

            $adjusted[] = [
                'value' => $value,
                'probability' => $probability,
            ];
        }

        $results = $this->monteCarlo->simulateOpportunities($adjusted, $run->simulation_count, $seed);

        if ($revenueAdjustment != 0.0) {
            $results = array_map(fn ($value) => max(0.0, $value + $revenueAdjustment), $results);
        }

        return $results;
    }

    public function statistics(ForecastRun $run, ForecastScenario $scenario, ?int $seed = null): array
    {
        $results = $this->simulate($run, $scenario, $seed);

        $target = $scenario->target_revenue ?? $run->target_revenue;

        return $this->monteCarlo->calculateStatistics(
            $results,
            $target !== null ? (float) $target : null
        );
    }
}
