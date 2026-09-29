<?php

namespace App\Services\Sales;

use App\Enums\OpportunityStatus;
use App\Enums\Role;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Collection;

class SalesPerformanceService
{
    public function forUser(User $user): array
    {
        $opportunities = Opportunity::query()
            ->where('user_id', $user->id)
            ->get(['id', 'status', 'value']);

        return $this->summarize($opportunities);
    }

    public function forUsers(Collection $users): Collection
    {
        $grouped = Opportunity::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->get(['id', 'user_id', 'status', 'value'])
            ->groupBy('user_id');

        $rows = $users->map(function (User $user) use ($grouped) {
            return [
                'user' => $user,
                'performance' => $this->summarize($grouped->get($user->id, collect())),
            ];
        })->values();

        $ranked = $rows->sortBy([
            ['performance.total_revenue', 'desc'],
            ['performance.win_rate', 'desc'],
            ['performance.won_deals', 'desc'],
        ])->values();

        return $ranked->map(function (array $row, int $index) {
            $row['performance']['rank'] = $index + 1;

            return $row;
        });
    }

    public function leaderboard(int $limit = 5): array
    {
        return $this->forUsers($this->salesUsers())
            ->take($limit)
            ->map(fn (array $row) => [
                'rank' => $row['performance']['rank'],
                'user' => [
                    'id' => $row['user']->id,
                    'name' => $row['user']->name,
                    'email' => $row['user']->email,
                ],
                'win_rate' => $row['performance']['win_rate'],
                'total_revenue' => $row['performance']['total_revenue'],
                'won_deals' => $row['performance']['won_deals'],
                'total_deals' => $row['performance']['total_deals'],
                'avg_deal_size' => $row['performance']['avg_deal_size'],
            ])
            ->all();
    }

    public function teamSummary(): array
    {
        $rows = $this->forUsers($this->salesUsers());

        $totalSales = $rows->count();
        $totalDeals = (int) $rows->sum('performance.total_deals');
        $wonDeals = (int) $rows->sum('performance.won_deals');
        $lostDeals = (int) $rows->sum('performance.lost_deals');
        $totalRevenue = (float) $rows->sum('performance.total_revenue');
        $closed = $wonDeals + $lostDeals;

        return [
            'total_sales' => $totalSales,
            'total_deals' => $totalDeals,
            'won_deals' => $wonDeals,
            'lost_deals' => $lostDeals,
            'avg_win_rate' => $totalSales > 0
                ? round((float) $rows->avg('performance.win_rate'), 2)
                : 0.0,
            'overall_win_rate' => $closed > 0 ? round(($wonDeals / $closed) * 100, 2) : 0.0,
            'total_revenue' => round($totalRevenue, 2),
            'avg_deal_size' => $wonDeals > 0 ? round($totalRevenue / $wonDeals, 2) : 0.0,
        ];
    }

    public function summarize(Collection $opportunities): array
    {
        $total = $opportunities->count();
        $won = $opportunities->where('status', OpportunityStatus::WON->value)->count();
        $lost = $opportunities->where('status', OpportunityStatus::LOST->value)->count();
        $closed = $won + $lost;
        $revenue = (float) $opportunities
            ->where('status', OpportunityStatus::WON->value)
            ->sum('value');

        return [
            'total_deals' => $total,
            'won_deals' => $won,
            'lost_deals' => $lost,
            'win_rate' => $closed > 0 ? round(($won / $closed) * 100, 2) : 0.0,
            'total_revenue' => round($revenue, 2),
            'avg_deal_size' => $won > 0 ? round($revenue / $won, 2) : 0.0,
        ];
    }

    private function salesUsers(): Collection
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::SALES->value))
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'status']);
    }
}
