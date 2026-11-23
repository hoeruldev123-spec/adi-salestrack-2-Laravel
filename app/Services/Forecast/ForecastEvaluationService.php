<?php

namespace App\Services\Forecast;

use App\Models\ForecastRun;
use App\Models\ForecastScenario;
use App\Models\Opportunity;
use App\Models\SalesTransaction;
use Carbon\Carbon;

class ForecastEvaluationService
{
    /**
     * Compare a series of forecast values against actual values.
     *
     * @param  array<int, float>  $forecast
     * @param  array<int, float>  $actual
     */
    public function evaluate(array $forecast, array $actual): array
    {
        if (count($forecast) !== count($actual) || count($forecast) === 0) {
            throw new \InvalidArgumentException('Forecast and actual series must have the same non-empty length');
        }

        $absoluteErrors = [];
        $percentageErrors = [];
        $squaredErrors = [];

        foreach ($forecast as $index => $forecastValue) {
            $actualValue = $actual[$index];
            $error = $actualValue - $forecastValue;
            $absoluteErrors[] = abs($error);
            $percentageErrors[] = $actualValue != 0 ? abs($error / $actualValue) : 0.0;
            $squaredErrors[] = $error ** 2;
        }

        $mae = array_sum($absoluteErrors) / count($absoluteErrors);
        $mape = (array_sum($percentageErrors) / count($percentageErrors)) * 100;
        $rmse = sqrt(array_sum($squaredErrors) / count($squaredErrors));

        $totalForecast = array_sum($forecast);
        $totalActual = array_sum($actual);
        $bias = $totalActual - $totalForecast;

        return [
            'mae' => round($mae, 2),
            'mape' => round($mape, 2),
            'rmse' => round($rmse, 2),
            'bias' => round($bias, 2),
            'total_forecast' => round($totalForecast, 2),
            'total_actual' => round($totalActual, 2),
        ];
    }

    /**
     * Evaluate a completed forecast run against actual revenue within its period.
     */
    public function evaluateRun(ForecastRun $run): array
    {
        if (! $run->result) {
            throw new \InvalidArgumentException('Forecast run has no result to evaluate');
        }

        $actual = $this->actualRevenue($run);

        if ($actual === null) {
            return [
                'status' => 'NO_ACTUAL_DATA',
                'message' => 'No paid sales transactions found within the forecast period',
            ];
        }

        $metrics = $this->evaluate(
            [(float) $run->result->mean],
            [$actual]
        );

        $run->update([
            'actual_revenue' => $actual,
            'mape' => $metrics['mape'],
        ]);

        return [
            'status' => 'EVALUATED',
            'forecast_type' => $run->forecast_type,
            'forecast_mean' => (float) $run->result->mean,
            'forecast_median' => (float) $run->result->median,
            'actual_revenue' => $actual,
            'absolute_error' => round(abs($actual - (float) $run->result->mean), 2),
            'metrics' => $metrics,
        ];
    }

    /**
     * Actual revenue for the run period, or null when no transaction exists.
     */
    public function actualRevenue(ForecastRun $run): ?float
    {
        $start = $run->forecastStart()->toDateString();
        $end = $run->forecastEnd()->toDateString();

        $actual = $this->actualRevenueForPeriod($start, $end);

        return $actual > 0 ? $actual : null;
    }

    /**
     * Actual revenue for a date range, preferring realized sales transactions
     * and falling back to WON opportunities when no transaction exists.
     */
    public function actualRevenueForPeriod(string $start, string $end): float
    {
        $query = SalesTransaction::query()
            ->where('status', 'PAID')
            ->whereBetween('transaction_date', [$start, $end]);

        if ($query->exists()) {
            return (float) $query->sum('total_amount');
        }

        $oppQuery = Opportunity::query()
            ->where('status', 'WON')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('actual_closing_date', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->whereNull('actual_closing_date')
                            ->whereBetween('closing_date', [$start, $end]);
                    })
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->whereNull('actual_closing_date')
                            ->whereNull('closing_date')
                            ->whereBetween('expected_close_date', [$start, $end]);
                    });
            });

        if ($oppQuery->exists()) {
            return (float) ($oppQuery->sum('estimated_value') ?: $oppQuery->sum('value'));
        }

        return 0.0;
    }

    /**
     * Monthly forecast vs actual comparison and MAPE evaluation.
     *
     * @return array<string, mixed>
     */
    public function monthlyEvaluation(ForecastRun $run): array
    {
        $start = $run->forecastStart()->copy()->startOfMonth();
        $end = $run->forecastEnd()->copy()->startOfMonth();
        $result = $run->result()->first();
        $forecastTotal = (float) ($run->predicted_revenue ?? $result?->mean ?? 0);

        $opportunities = app(OpportunityForecastService::class)->opportunities($run);
        $expectedByMonth = [];

        foreach ($opportunities as $opportunity) {
            if (! $opportunity['closing_date']) {
                continue;
            }

            $month = Carbon::parse($opportunity['closing_date'])->format('Y-m');
            $expectedByMonth[$month] = ($expectedByMonth[$month] ?? 0)
                + (float) $opportunity['value'] * (float) $opportunity['probability'];
        }

        $expectedTotal = array_sum($expectedByMonth);
        $monthCount = max(1, (int) $start->diffInMonths($end) + 1);
        $periods = [];
        $validErrors = [];
        $totalActual = 0.0;

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $monthKey = $cursor->format('Y-m');
            $monthStart = $cursor->copy()->startOfMonth()->toDateString();
            $monthEnd = $cursor->copy()->endOfMonth()->toDateString();
            $actual = $this->actualRevenueForPeriod($monthStart, $monthEnd);

            $forecast = $expectedTotal > 0
                ? (($expectedByMonth[$monthKey] ?? 0) / $expectedTotal) * $forecastTotal
                : $forecastTotal / $monthCount;

            $error = $actual > 0 ? abs($actual - $forecast) / $actual * 100 : null;

            if ($error !== null) {
                $validErrors[] = $error;
            }

            $totalActual += $actual;

            $periods[] = [
                'period' => $monthKey,
                'label' => $cursor->copy()->locale('id')->translatedFormat('F Y'),
                'forecast' => round($forecast, 2),
                'actual' => round($actual, 2),
                'error_percentage' => $error !== null ? round($error, 2) : null,
                'status' => $actual > 0 ? 'EVALUATED' : 'NO_ACTUAL',
            ];
        }

        $mape = count($validErrors) > 0
            ? round(array_sum($validErrors) / count($validErrors), 2)
            : null;

        if ($mape !== null) {
            $run->update([
                'actual_revenue' => round($totalActual, 2),
                'mape' => $mape,
            ]);
        }

        return [
            'forecast_run_id' => $run->id,
            'status' => $mape !== null ? 'EVALUATED' : 'NO_ACTUAL_DATA',
            'forecast_period' => [
                'start' => $run->forecastStart()->toDateString(),
                'end' => $run->forecastEnd()->toDateString(),
            ],
            'periods' => $periods,
            'mape' => $mape,
            'interpretation' => $mape !== null
                ? $this->mapeInterpretation($mape)
                : 'MAPE tidak dapat dihitung karena belum ada pendapatan aktual pada periode forecast.',
            'evaluated_periods' => count($validErrors),
            'skipped_periods' => count($periods) - count($validErrors),
            'forecast_revenue' => round($forecastTotal, 2),
            'actual_revenue' => round($totalActual, 2),
            'total_forecast' => round(array_sum(array_column($periods, 'forecast')), 2),
            'total_actual' => round($totalActual, 2),
        ];
    }

    public function mapeInterpretation(float $mape): string
    {
        return match (true) {
            $mape < 10 => 'Akurasi sangat baik',
            $mape <= 20 => 'Akurasi baik',
            $mape <= 50 => 'Akurasi cukup',
            default => 'Akurasi rendah',
        };
    }

    /**
     * Historical evaluation: run the same simulation retrospectively on
     * revenue from previous periods and compare with actual results.
     *
     * @return array{periods: array, metrics: array, total_forecast: float, total_actual: float}
     */
    public function historicalBacktest(ForecastRun $run, int $periods = 6, ?int $seed = null): array
    {
        $monthly = app(HistoricalForecastService::class)->monthlyRevenue($run);

        if (count($monthly) < $periods + 2) {
            throw new \InvalidArgumentException('Not enough historical data for backtest');
        }

        $monteCarlo = app(MonteCarloService::class);

        $perPeriod = [];
        $forecastSeries = [];
        $actualSeries = [];

        for ($i = 0; $i < $periods; $i++) {
            $training = array_slice($monthly, 0, count($monthly) - $periods + $i);
            $actualValue = $monthly[count($monthly) - $periods + $i];

            $results = $monteCarlo->simulateHistorical(
                $training,
                min($run->simulation_count, 10000),
                10,
                $seed
            );
            $statistics = $monteCarlo->calculateStatistics($results);

            $forecastSeries[] = $statistics['mean'];
            $actualSeries[] = $actualValue;

            $perPeriod[] = [
                'period_index' => $i + 1,
                'forecast' => $statistics['mean'],
                'actual' => $actualValue,
                'absolute_error' => round(abs($actualValue - $statistics['mean']), 2),
            ];
        }

        return [
            'periods' => $perPeriod,
            'metrics' => $this->evaluate($forecastSeries, $actualSeries),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function compareScenarioToBaseline(ForecastRun $run, ForecastScenario $scenario, ?int $seed = null): array
    {
        $base = app(OpportunityForecastService::class)->statistics($run, $seed);
        $scenarioStats = app(ScenarioForecastService::class)->statistics($run, $scenario, $seed);

        $baseMean = (float) $base['mean'];
        $scenarioMean = (float) $scenarioStats['mean'];

        return [
            'scenario_name' => $scenario->name,
            'baseline_forecast' => $baseMean,
            'scenario_forecast' => $scenarioMean,
            'difference' => round($scenarioMean - $baseMean, 2),
            'potential_increase' => $scenarioMean > $baseMean ? round($scenarioMean - $baseMean, 2) : 0,
            'baseline_target_probability' => $base['target_probability'],
            'scenario_target_probability' => $scenarioStats['target_probability'],
        ];
    }
}
