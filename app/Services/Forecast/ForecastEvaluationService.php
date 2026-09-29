<?php

namespace App\Services\Forecast;

use App\Models\ForecastRun;
use App\Models\ForecastScenario;
use App\Models\SalesTransaction;

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

        return [
            'status' => 'EVALUATED',
            'forecast_type' => $run->forecast_type,
            'forecast_mean' => (float) $run->result->mean,
            'forecast_median' => (float) $run->result->median,
            'actual_revenue' => $actual,
            'absolute_error' => round(abs($actual - (float) $run->result->mean), 2),
            'metrics' => $this->evaluate(
                [(float) $run->result->mean],
                [$actual]
            ),
        ];
    }

    /**
     * Actual revenue for the run period, or null when no transaction exists.
     */
    public function actualRevenue(ForecastRun $run): ?float
    {
        $total = SalesTransaction::query()
            ->where('status', 'PAID')
            ->whereBetween('transaction_date', [$run->period_start, $run->period_end])
            ->sum('total_amount');

        return (float) $total;
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
