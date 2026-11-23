<?php

namespace App\Services\Forecast;

use App\Enums\TransactionStatus;
use App\Models\ForecastRun;
use App\Models\SalesTransaction;
use Illuminate\Support\Facades\DB;

class HistoricalForecastService
{
    public function __construct(private readonly MonteCarloService $monteCarlo) {}

    /**
     * Monthly historical revenue used as the base of the simulation.
     *
     * @return array<int, float>
     */
    public function monthlyRevenue(ForecastRun $run): array
    {
        $baseQuery = SalesTransaction::query()
            ->select(DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as period'), DB::raw('SUM(total_amount) as total'))
            ->where('status', TransactionStatus::PAID->value);

        $historicalStart = $run->historicalStart();
        $historicalEnd = $run->historicalEnd();

        // 1. Use the configured historical period when it spans at least 2 months.
        $rangeRows = (clone $baseQuery)
            ->whereBetween('transaction_date', [$historicalStart->toDateString(), $historicalEnd->toDateString()])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        if ($rangeRows->count() >= 2) {
            return $rangeRows->pluck('total')->map(fn ($value) => (float) $value)->all();
        }

        // 2. Otherwise (e.g. forward-looking forecast or single-month forecast period),
        // use historical monthly revenue prior to forecast start:
        $priorRows = (clone $baseQuery)
            ->where('transaction_date', '<', $run->forecastStart()->toDateString())
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        if ($priorRows->count() >= 2) {
            return $priorRows->pluck('total')->map(fn ($value) => (float) $value)->all();
        }

        // 3. Fallback: all available historical monthly revenue
        return (clone $baseQuery)
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->pluck('total')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @return array<int, float>
     */
    public function simulate(ForecastRun $run, ?int $seed = null): array
    {
        $monthly = $this->monthlyRevenue($run);

        $start = $run->forecastStart()->copy()->startOfMonth();
        $end = $run->forecastEnd()->copy()->startOfMonth();
        $months = max(1, $start->diffInMonths($end) + 1);

        return $this->monteCarlo->simulateHistorical(
            $monthly,
            $run->simulation_count,
            10,
            $seed,
            $months
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
