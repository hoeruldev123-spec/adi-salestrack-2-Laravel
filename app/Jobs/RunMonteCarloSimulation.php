<?php

namespace App\Jobs;

use App\Enums\ForecastRunStatus;
use App\Enums\ForecastType;
use App\Models\ForecastResult;
use App\Models\ForecastRun;
use App\Services\Forecast\CombinedForecastService;
use App\Services\Forecast\ForecastEvaluationService;
use App\Services\Forecast\HistoricalForecastService;
use App\Services\Forecast\MonteCarloService;
use App\Services\Forecast\OpportunityForecastService;
use App\Services\Forecast\RecommendationService;
use App\Services\Forecast\ScenarioForecastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunMonteCarloSimulation implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public readonly ForecastRun $forecastRun) {}

    public function handle(): void
    {
        $this->forecastRun->refresh();
        $this->forecastRun->update([
            'status' => ForecastRunStatus::PROCESSING->value,
            'started_at' => now(),
        ]);

        $this->clearPreviousSimulationData();

        $monteCarlo = app(MonteCarloService::class);
        $warnings = [];
        $targetRevenue = $this->forecastRun->target_revenue !== null
            ? (float) $this->forecastRun->target_revenue
            : null;

        $totals = match ($this->forecastRun->forecast_type) {
            ForecastType::OPPORTUNITY->value => $this->simulateOpportunities($warnings),
            ForecastType::HISTORICAL->value => app(HistoricalForecastService::class)->simulate($this->forecastRun),
            ForecastType::COMBINED->value => app(CombinedForecastService::class)->simulate($this->forecastRun),
            ForecastType::SCENARIO->value => $this->simulateScenarios(),
            default => throw new \InvalidArgumentException('Unknown forecast type'),
        };

        if (empty($totals)) {
            $totals = array_fill(0, max(1, (int) $this->forecastRun->simulation_count), 0.0);
            $warnings[] = 'Tidak ada data yang dapat disimulasikan untuk parameter forecast ini.';
        }

        $statistics = $monteCarlo->calculateStatistics($totals, $targetRevenue);
        $this->saveResult($statistics);
        $this->persistSimulationTotals($totals, $targetRevenue);

        $evaluation = app(ForecastEvaluationService::class)->monthlyEvaluation($this->forecastRun);
        $actualRevenue = $evaluation['actual_revenue'] > 0 ? (float) $evaluation['actual_revenue'] : null;
        $mape = $evaluation['mape'];

        $this->forecastRun->update([
            'predicted_revenue' => $statistics['mean'],
            'best_case' => $statistics['p90'],
            'worst_case' => $statistics['p10'],
            'most_likely' => $statistics['median'],
            'actual_revenue' => $actualRevenue,
            'mape' => $mape,
            'warnings' => array_values(array_unique($warnings)),
            'status' => ForecastRunStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);

        app(RecommendationService::class)->generateForRun($this->forecastRun);
    }

    public function failed(?Throwable $e): void
    {
        $this->forecastRun->update([
            'status' => ForecastRunStatus::FAILED->value,
            'completed_at' => now(),
            'warnings' => array_merge($this->forecastRun->warnings ?? [], [
                $e?->getMessage() ?: 'Terjadi kesalahan saat menjalankan simulasi.',
            ]),
        ]);
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, float>
     */
    private function simulateOpportunities(array &$warnings): array
    {
        $chunkSize = max(100, (int) config('salestrack.forecast.detail_chunk_size', 1000));
        $storeDetails = (bool) config('salestrack.forecast.store_detailed_results', true);
        $detailBuffer = [];

        $simulation = app(OpportunityForecastService::class)->simulateWithDetails(
            $this->forecastRun,
            null,
            function (int $iteration, float $total, array $details) use (
                &$detailBuffer,
                $chunkSize,
                $storeDetails
            ) {
                if (! $storeDetails) {
                    return;
                }

                $now = now();

                foreach ($details as $detail) {
                    if ($detail['opportunity_id'] === null) {
                        continue;
                    }

                    $detailBuffer[] = [
                        'forecast_run_id' => $this->forecastRun->id,
                        'opportunity_id' => $detail['opportunity_id'],
                        'simulation_iteration' => $iteration,
                        'random_number' => $detail['random_number'],
                        'probability' => $detail['probability'],
                        'simulated_value' => $detail['simulated_value'],
                        'is_success' => $detail['is_success'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($detailBuffer) >= $chunkSize) {
                    DB::table('forecast_results')->insert($detailBuffer);
                    $detailBuffer = [];
                }
            }
        );

        if ($detailBuffer !== []) {
            DB::table('forecast_results')->insert($detailBuffer);
        }

        $warnings = array_merge($warnings, $simulation['warnings']);

        return $simulation['totals'];
    }

    /**
     * @return array<int, float>
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
        $combined = [];

        foreach ($scenarios as $scenario) {
            $combined = array_merge($combined, $scenarioService->simulate($this->forecastRun, $scenario));
        }

        return $combined;
    }

    private function clearPreviousSimulationData(): void
    {
        $this->forecastRun->simulations()->delete();
        $this->forecastRun->results()->whereNotNull('opportunity_id')->delete();
        $this->forecastRun->results()->whereNull('opportunity_id')->delete();
        $this->forecastRun->recommendations()->delete();
    }

    private function saveResult(array $statistics): void
    {
        ForecastResult::create([
            'forecast_run_id' => $this->forecastRun->id,
            'opportunity_id' => null,
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
        ]);
    }

    /**
     * @param  array<int, float>  $totals
     */
    private function persistSimulationTotals(array $totals, ?float $targetRevenue): void
    {
        $chunkSize = max(100, (int) config('salestrack.forecast.detail_chunk_size', 1000));
        $buffer = [];
        $now = now();

        foreach ($totals as $index => $total) {
            $buffer[] = [
                'forecast_run_id' => $this->forecastRun->id,
                'simulation_number' => $index + 1,
                'total_revenue' => $total,
                'target_achieved' => $targetRevenue !== null && $total >= $targetRevenue,
                'created_at' => $now,
            ];

            if (count($buffer) >= $chunkSize) {
                DB::table('forecast_simulations')->insert($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('forecast_simulations')->insert($buffer);
        }
    }
}
