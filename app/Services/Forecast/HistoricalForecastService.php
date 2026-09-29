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
        $rows = SalesTransaction::query()
            ->select(DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as period'), DB::raw('SUM(total_amount) as total'))
            ->where('status', TransactionStatus::PAID->value)
            ->whereBetween('transaction_date', [$run->period_start, $run->period_end])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $rows->pluck('total')->map(fn ($value) => (float) $value)->all();
    }

    /**
     * @return array<int, float>
     */
    public function simulate(ForecastRun $run, ?int $seed = null): array
    {
        $monthly = $this->monthlyRevenue($run);

        return $this->monteCarlo->simulateHistorical(
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
