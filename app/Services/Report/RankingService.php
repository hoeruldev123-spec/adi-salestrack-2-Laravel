<?php

namespace App\Services\Report;

use App\Enums\OpportunityStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\Periods;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RankingService
{
    use Periods;

    public function sales(array $filters = []): array
    {
        [$start, $end] = $this->resolveRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            12
        );

        $keys = $this->monthKeys($start, $end);
        $previousKeys = $this->previousPeriodKeys($keys);
        $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;

        $rows = $this->periodOpportunities($start, $end)->groupBy('user_id');
        $previousRows = $this->opportunitiesByMonths($previousKeys)->groupBy('user_id');

        $users = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::SALES->value))
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        $ranking = $users->map(function (User $user) use ($rows, $previousRows, $days) {
            $opportunities = $rows->get($user->id, collect());
            $summary = $this->summarize($opportunities);
            $previousRevenue = (float) $previousRows->get($user->id, collect())
                ->where('status', OpportunityStatus::WON->value)
                ->sum('value');

            $pipeline = (float) $opportunities
                ->where('status', OpportunityStatus::OPEN->value)
                ->sum('value');

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'total_deals' => $summary['total_deals'],
                'won_deals' => $summary['won_deals'],
                'lost_deals' => $summary['lost_deals'],
                'win_rate' => $summary['win_rate'],
                'total_revenue' => $summary['total_revenue'],
                'avg_deal_size' => $summary['avg_deal_size'],
                'pipeline_value' => round($pipeline, 2),
                'pipeline_velocity' => round(($pipeline * $summary['win_rate'] / 100) / max($days, 1), 2),
                'growth_percent' => $this->growthPercent($summary['total_revenue'], $previousRevenue),
            ];
        });

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'data' => $this->rank($ranking),
        ];
    }

    public function principals(array $filters = []): array
    {
        [$start, $end] = $this->resolveRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            12
        );

        $keys = $this->monthKeys($start, $end);
        $previousKeys = $this->previousPeriodKeys($keys);

        $rows = $this->periodOpportunities($start, $end)->groupBy('principal_id');
        $previousRows = $this->opportunitiesByMonths($previousKeys)->groupBy('principal_id');
        $totalAccounts = Company::query()->count();

        $principals = MasterPrincipal::query()->orderBy('id')->get(['id', 'name']);

        $ranking = $principals->map(function (MasterPrincipal $principal) use ($rows, $previousRows, $totalAccounts) {
            $opportunities = $rows->get($principal->id, collect());
            $summary = $this->summarize($opportunities);
            $previousRevenue = (float) $previousRows->get($principal->id, collect())
                ->where('status', OpportunityStatus::WON->value)
                ->sum('value');

            $wonAccounts = $opportunities
                ->where('status', OpportunityStatus::WON->value)
                ->pluck('company_id')
                ->filter()
                ->unique()
                ->count();

            return [
                'principal' => [
                    'id' => $principal->id,
                    'name' => $principal->name,
                ],
                'total_deals' => $summary['total_deals'],
                'won_deals' => $summary['won_deals'],
                'win_rate' => $summary['win_rate'],
                'total_revenue' => $summary['total_revenue'],
                'avg_deal_size' => $summary['avg_deal_size'],
                'won_accounts' => $wonAccounts,
                'account_penetration' => $totalAccounts > 0
                    ? round(($wonAccounts / $totalAccounts) * 100, 2)
                    : 0.0,
                'growth_percent' => $this->growthPercent($summary['total_revenue'], $previousRevenue),
            ];
        });

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'data' => $this->rank($ranking),
        ];
    }

    public function accounts(array $filters = []): array
    {
        [$start, $end] = $this->resolveRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            12
        );

        $keys = $this->monthKeys($start, $end);
        $previousKeys = $this->previousPeriodKeys($keys);

        $rows = $this->periodOpportunities($start, $end)->groupBy('company_id');
        $previousRows = $this->opportunitiesByMonths($previousKeys)->groupBy('company_id');

        $openPipeline = Opportunity::query()
            ->where('status', OpportunityStatus::OPEN->value)
            ->selectRaw('company_id, COALESCE(SUM(value), 0) as total')
            ->groupBy('company_id')
            ->get()
            ->pluck('total', 'company_id');

        $accounts = Company::query()->orderBy('id')->get(['id', 'name']);

        $ranking = $accounts->map(function (Company $account) use ($rows, $previousRows, $openPipeline) {
            $opportunities = $rows->get($account->id, collect());
            $summary = $this->summarize($opportunities);
            $previousRevenue = (float) $previousRows->get($account->id, collect())
                ->where('status', OpportunityStatus::WON->value)
                ->sum('value');
            $growthPotential = round((float) ($openPipeline->get($account->id) ?? 0), 2);

            return [
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'total_deals' => $summary['total_deals'],
                'won_deals' => $summary['won_deals'],
                'win_rate' => $summary['win_rate'],
                'total_revenue' => $summary['total_revenue'],
                'segment' => $this->segment($summary['total_revenue']),
                'growth_percent' => $this->growthPercent($summary['total_revenue'], $previousRevenue),
                'growth_potential' => $growthPotential,
            ];
        });

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'data' => $this->rank($ranking),
        ];
    }

    private function periodOpportunities(string $start, string $end): Collection
    {
        return Opportunity::query()
            ->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59'])
            ->get(['id', 'user_id', 'company_id', 'principal_id', 'status', 'value']);
    }

    private function opportunitiesByMonths(array $keys): Collection
    {
        if ($keys === []) {
            return collect();
        }

        $start = Carbon::parse($keys[0])->startOfMonth();
        $end = Carbon::parse($keys[count($keys) - 1])->endOfMonth();

        return Opportunity::query()
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->get(['id', 'user_id', 'company_id', 'principal_id', 'status', 'value']);
    }

    private function summarize(Collection $opportunities): array
    {
        $total = $opportunities->count();
        $won = $opportunities->where('status', OpportunityStatus::WON->value)->count();
        $lost = $opportunities->where('status', OpportunityStatus::LOST->value)->count();
        $closed = $won + $lost;
        $revenue = (float) $opportunities->where('status', OpportunityStatus::WON->value)->sum('value');

        return [
            'total_deals' => $total,
            'won_deals' => $won,
            'lost_deals' => $lost,
            'win_rate' => $closed > 0 ? round(($won / $closed) * 100, 2) : 0.0,
            'total_revenue' => round($revenue, 2),
            'avg_deal_size' => $won > 0 ? round($revenue / $won, 2) : 0.0,
        ];
    }

    private function rank(Collection $rows): array
    {
        return $rows
            ->sortBy([
                ['total_revenue', 'desc'],
                ['win_rate', 'desc'],
                ['total_deals', 'desc'],
            ])
            ->values()
            ->map(fn (array $row, int $index) => ['rank' => $index + 1] + $row)
            ->all();
    }
}
