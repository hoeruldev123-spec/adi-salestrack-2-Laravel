<?php

namespace App\Services\Report;

use App\Enums\OpportunityStatus;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use App\Support\Periods;
use Illuminate\Support\Collection;

class RevenueReportService
{
    use Periods;

    public function overview(array $filters = []): array
    {
        [$start, $end] = $this->resolveRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            12
        );

        $keys = $this->monthKeys($start, $end);
        $previousKeys = $this->previousPeriodKeys($keys);

        $won = $this->wonOpportunities();
        $current = $won->filter(fn (Opportunity $o) => in_array($this->revenueMonth($o), $keys, true));
        $previous = $won->filter(fn (Opportunity $o) => in_array($this->revenueMonth($o), $previousKeys, true));

        $totalRevenue = (float) $current->sum('value');
        $previousRevenue = (float) $previous->sum('value');
        $deals = $current->count();

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'metrics' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_deals' => $deals,
                'avg_deal_size' => $deals > 0 ? round($totalRevenue / $deals, 2) : 0.0,
                'growth_percent' => $this->growthPercent($totalRevenue, $previousRevenue),
                'previous_period_revenue' => round($previousRevenue, 2),
            ],
            'by_month' => $this->byMonth($current, $keys),
            'by_principal' => $this->groupRevenue($current, 'principal_id', MasterPrincipal::class),
            'by_account' => $this->groupRevenue($current, 'company_id', Company::class),
        ];
    }

    public function forecast(): array
    {
        $months = (int) config('salestrack.forecast.months', 6);
        $winRate = (float) config('salestrack.forecast.historical_win_rate', 0.3);

        $all = Opportunity::query()->get(['id', 'status', 'value', 'probability', 'expected_close_date']);
        $open = $all->filter(fn (Opportunity $o) => $this->isActivePipeline($o->status));

        $pipelineValue = (float) $open->sum('value');
        $weightedForecast = round((float) $open->sum(fn (Opportunity $o) => $o->value * $o->probability / 100), 2);

        $keys = [];
        $cursor = now()->startOfMonth();
        for ($i = 0; $i < $months; $i++) {
            $keys[] = $cursor->copy()->addMonths($i)->format('Y-m');
        }

        $forecastMonths = array_map(function (string $key) use ($open, $winRate) {
            $rows = $open->filter(
                fn (Opportunity $o) => $o->expected_close_date?->format('Y-m') === $key
            );

            $pipeline = (float) $rows->sum('value');
            $weighted = round((float) $rows->sum(fn (Opportunity $o) => $o->value * $o->probability / 100), 2);

            return [
                'month' => $key,
                'deals' => $rows->count(),
                'pipeline_value' => round($pipeline, 2),
                'weighted_value' => $weighted,
                'forecast_value' => round($pipeline * $winRate, 2),
            ];
        }, $keys);

        return [
            'historical_win_rate' => $winRate,
            'win_probability_by_status' => $this->winProbabilityByStatus($all),
            'forecast_by_status' => $this->forecastByStatus($open),
            'forecast_6_months' => $forecastMonths,
            'pipeline_value' => round($pipelineValue, 2),
            'weighted_forecast' => $weightedForecast,
        ];
    }

    public function comparison(array $filters = []): array
    {
        [$start, $end] = $this->resolveRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            12
        );

        $keys = $this->monthKeys($start, $end);
        $previousKeys = $this->previousPeriodKeys($keys);
        $yoyKeys = $this->shiftYear($keys);

        $won = $this->wonOpportunities();

        $current = $won->filter(fn (Opportunity $o) => in_array($this->revenueMonth($o), $keys, true));
        $previous = $won->filter(fn (Opportunity $o) => in_array($this->revenueMonth($o), $previousKeys, true));
        $lastYear = $won->filter(fn (Opportunity $o) => in_array($this->revenueMonth($o), $yoyKeys, true));

        $currentRevenue = (float) $current->sum('value');
        $previousRevenue = (float) $previous->sum('value');
        $lastYearRevenue = (float) $lastYear->sum('value');

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'monthly' => $this->byMonth($current, $keys),
            'quarterly' => $this->byQuarter($current, $keys),
            'previous_period' => [
                'revenue' => round($previousRevenue, 2),
                'growth_percent' => $this->growthPercent($currentRevenue, $previousRevenue),
            ],
            'yoy' => [
                'current' => round($currentRevenue, 2),
                'previous' => round($lastYearRevenue, 2),
                'growth_percent' => $this->growthPercent($currentRevenue, $lastYearRevenue),
            ],
        ];
    }

    private function winProbabilityByStatus(Collection $all): array
    {
        $result = [];

        foreach (OpportunityStatus::cases() as $status) {
            $rows = $all->where('status', $status->value);

            $result[$status->value] = match ($status) {
                OpportunityStatus::WON => 100.0,
                OpportunityStatus::LOST, OpportunityStatus::CANCELLED => 0.0,
                default => $this->weightedProbability($rows),
            };
        }

        return $result;
    }

    private function forecastByStatus(Collection $open): array
    {
        return collect(OpportunityStatus::cases())
            ->filter(fn (OpportunityStatus $status) => $this->isActivePipeline($status->value))
            ->map(function (OpportunityStatus $status) use ($open) {
                $rows = $open->where('status', $status->value);

                return [
                    'status' => $status->value,
                    'deals' => $rows->count(),
                    'pipeline_value' => round((float) $rows->sum('value'), 2),
                    'weighted_value' => round((float) $rows->sum(fn (Opportunity $o) => $o->value * $o->probability / 100), 2),
                ];
            })
            ->values()
            ->all();
    }

    private function weightedProbability(Collection $rows): float
    {
        $value = (float) $rows->sum('value');

        if ($value <= 0) {
            return 0.0;
        }

        return round(((float) $rows->sum(fn (Opportunity $o) => $o->value * $o->probability)) / $value, 2);
    }

    private function wonOpportunities(): Collection
    {
        return Opportunity::query()
            ->where('status', OpportunityStatus::WON->value)
            ->get(['id', 'status', 'value', 'probability', 'expected_close_date', 'updated_at', 'principal_id', 'company_id']);
    }

    private function byMonth(Collection $rows, array $keys): array
    {
        $grouped = $rows->groupBy(fn (Opportunity $o) => $this->revenueMonth($o));

        return array_map(fn (string $key) => [
            'month' => $key,
            'deals' => $grouped->has($key) ? $grouped->get($key)->count() : 0,
            'revenue' => $grouped->has($key) ? round((float) $grouped->get($key)->sum('value'), 2) : 0.0,
        ], $keys);
    }

    private function byQuarter(Collection $rows, array $keys): array
    {
        $grouped = $rows->groupBy(function (Opportunity $o) {
            $month = $this->revenueMonth($o);

            return sprintf('%s-Q%d', substr($month, 0, 4), intdiv(((int) substr($month, 5)) - 1, 3) + 1);
        });

        $quarters = [];
        foreach ($keys as $key) {
            $label = sprintf('%s-Q%d', substr($key, 0, 4), intdiv(((int) substr($key, 5)) - 1, 3) + 1);
            if (! isset($quarters[$label])) {
                $quarters[$label] = ['quarter' => $label, 'deals' => 0, 'revenue' => 0.0];
            }
        }

        foreach ($quarters as $label => $row) {
            if ($grouped->has($label)) {
                $quarters[$label]['deals'] = $grouped->get($label)->count();
                $quarters[$label]['revenue'] = round((float) $grouped->get($label)->sum('value'), 2);
            }
        }

        return array_values($quarters);
    }

    private function groupRevenue(Collection $rows, string $foreignKey, string $model): array
    {
        $grouped = $rows->filter(fn (Opportunity $o) => $o->{$foreignKey} !== null)
            ->groupBy(fn (Opportunity $o) => $o->{$foreignKey});

        if ($grouped->isEmpty()) {
            return [];
        }

        $names = $model::query()
            ->whereIn('id', $grouped->keys())
            ->pluck('name', 'id');

        return $grouped
            ->map(function (Collection $items, string $id) use ($names, $foreignKey) {
                $revenue = (float) $items->sum('value');

                return [
                    'id' => (int) $id,
                    'name' => $names->get($id) ?? ($foreignKey === 'company_id' ? 'Unknown account' : 'Unknown principal'),
                    'deals' => $items->count(),
                    'revenue' => round($revenue, 2),
                ];
            })
            ->values()
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }
}
