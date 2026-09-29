<?php

namespace App\Jobs;

use App\Enums\ForecastRunStatus;
use App\Enums\ForecastType;
use App\Models\ForecastResult;
use App\Models\ForecastRun;
use App\Services\Forecast\CombinedForecastService;
use App\Services\Forecast\HistoricalForecastService;
use App\Services\Forecast\MonteCarloService;
use App\Services\Forecast\OpportunityForecastService;
use App\Services\Forecast\ScenarioForecastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunMonteCarloSimulation implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public readonly ForecastRun $forecastRun) {}

    public function handle(): void
    {
        $this->forecastRun->update([
            'status' => ForecastRunStatus::PROCESSING->value,
            'started_at' => now(),
        ]);

        $statistics = match ($this->forecastRun->forecast_type) {
            ForecastType::HISTORICAL->value => app(HistoricalForecastService::class)->statistics($this->forecastRun),
            ForecastType::OPPORTUNITY->value => app(OpportunityForecastService::class)->statistics($this->forecastRun),
            ForecastType::COMBINED->value => app(CombinedForecastService::class)->statistics($this->forecastRun),
            ForecastType::SCENARIO->value => $this->simulateScenarios(),
            default => throw new \InvalidArgumentException('Unknown forecast type'),
        };

        $this->saveResult($statistics);

        $this->forecastRun->update([
            'status' => ForecastRunStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $e): void
    {
        $this->forecastRun->update([
            'status' => ForecastRunStatus::FAILED->value,
            'completed_at' => now(),
        ]);
    }

    private function saveResult(array $statistics): void
    {
        ForecastResult::updateOrCreate(
            ['forecast_run_id' => $this->forecastRun->id],
            [
                'minimum' => $statistics['minimum'],
                'maximum' => $statistics['maximum'],
                'mean' => $statistics['mean'],
                'median' => $statistics['median'],
                'p10' => $statistics['p10'],
                'p25' => $statistics['p25'],
                'p50' => $statistics['p50'],
                'p75' => $statistics['p75'],
                'p90' => $statistics['p90'],
                'p95' => $statistics['p95'],
                'target_probability' => $statistics['target_probability'],
            ]
        );
    }

    /**
     * Scenario runs: run every scenario and aggregate the combined result set.
     */
    private function simulateScenarios(): array
    {
        $scenarios = $this->forecastRun->scenarios;

        if ($scenarios->isEmpty()) {
            $scenarios = collect([
                $this->forecastRun->scenarios()->create([
                    'name' => 'Baseline',
                    'description' => 'Baseline otomatis (tanpa penyesuaian)',
                    'win_rate_adjustment' => 0,
                    'revenue_adjustment' => 0,
                    'opportunity_adjustment' => 0,
                ]),
            ]);
        }

        $scenarioService = app(ScenarioForecastService::class);
        $monteCarlo = app(MonteCarloService::class);

        $combined = [];

        foreach ($scenarios as $scenario) {
            $combined = array_merge($combined, $scenarioService->simulate($this->forecastRun, $scenario));
        }

        return $monteCarlo->calculateStatistics(
            $combined,
            $this->forecastRun->target_revenue !== null ? (float) $this->forecastRun->target_revenue : null
        );
    }
}
