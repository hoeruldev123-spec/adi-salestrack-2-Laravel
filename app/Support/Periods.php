<?php

namespace App\Support;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use Carbon\Carbon;

trait Periods
{
    protected function resolveRange(?string $start, ?string $end, int $defaultMonths): array
    {
        if ($start === null && $end === null && $defaultMonths === 1) {
            return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        }

        $end ??= now()->toDateString();
        $start ??= Carbon::parse($end)->startOfMonth()->subMonths($defaultMonths - 1)->toDateString();

        return [$start, $end];
    }

    /**
     * @return array<int, string>
     */
    protected function monthKeys(string $start, string $end, int $max = 36): array
    {
        $keys = [];
        $cursor = Carbon::parse($start)->startOfMonth();
        $last = Carbon::parse($end)->startOfMonth();

        while ($cursor->lte($last) && count($keys) < $max) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    protected function previousPeriodKeys(array $keys): array
    {
        $count = count($keys);
        if ($count === 0) {
            return [];
        }

        $first = Carbon::parse($keys[0])->startOfMonth()->subMonths($count);

        return $this->monthKeys($first->toDateString(), $first->copy()->addMonths($count - 1)->toDateString(), $count);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    protected function shiftYear(array $keys, int $shift = -1): array
    {
        return array_map(
            fn (string $key) => Carbon::parse($key.'-01')->addYears($shift)->format('Y-m'),
            $keys
        );
    }

    protected function revenueMonth(Opportunity $opportunity): string
    {
        return ($opportunity->expected_close_date ?? $opportunity->updated_at)->format('Y-m');
    }

    protected function growthPercent(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? null : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    protected function segment(float $revenue): string
    {
        $thresholds = config('salestrack.segmentation');

        if ($revenue >= $thresholds['platinum']) {
            return 'platinum';
        }

        if ($revenue >= $thresholds['gold']) {
            return 'gold';
        }

        if ($revenue >= $thresholds['silver']) {
            return 'silver';
        }

        return 'bronze';
    }

    protected function isWon(string $status): bool
    {
        return $status === OpportunityStatus::WON->value;
    }

    protected function isLost(string $status): bool
    {
        return $status === OpportunityStatus::LOST->value;
    }

    protected function isActivePipeline(string $status): bool
    {
        return in_array($status, [
            OpportunityStatus::OPEN->value,
            OpportunityStatus::ON_HOLD->value,
        ], true);
    }
}
