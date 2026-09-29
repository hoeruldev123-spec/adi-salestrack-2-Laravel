<?php

namespace App\Services\Dashboard;

use App\Enums\OpportunityStatus;
use App\Enums\Role;
use App\Http\Resources\Opportunity\OpportunityResource;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Report\RevenueReportService;
use App\Services\Sales\SalesPerformanceService;
use App\Support\Periods;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    use Periods;

    public function __construct(
        private readonly SalesPerformanceService $performanceService,
        private readonly RevenueReportService $revenueReportService,
    ) {}

    public function sales(User $user): array
    {
        $mine = Opportunity::query()->where('user_id', $user->id);

        $total = (clone $mine)->count();
        $won = (clone $mine)->where('status', OpportunityStatus::WON->value);
        $lost = (clone $mine)->where('status', OpportunityStatus::LOST->value);
        $open = (clone $mine)->where('status', OpportunityStatus::OPEN->value);

        $wonCount = $won->count();
        $lostCount = $lost->count();
        $closed = $wonCount + $lostCount;

        $recent = (clone $mine)
            ->with(['company:id,name', 'principal:id,name'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $upcoming = (clone $mine)
            ->with(['company:id,name'])
            ->where('status', OpportunityStatus::OPEN->value)
            ->whereNotNull('expected_close_date')
            ->where('expected_close_date', '>=', now()->toDateString())
            ->orderBy('expected_close_date')
            ->limit(5)
            ->get();

        return [
            'total_opportunities' => $total,
            'total_pipeline_value' => round((float) $open->sum('value'), 2),
            'won_count' => $wonCount,
            'won_value' => round((float) $won->sum('value'), 2),
            'lost_count' => $lostCount,
            'open_count' => (clone $mine)->where('status', OpportunityStatus::OPEN->value)->count(),
            'win_rate' => $closed > 0 ? round(($wonCount / $closed) * 100, 2) : 0.0,
            'pipeline_by_status' => $this->pipelineByStatus($user->id),
            'recent_opportunities' => OpportunityResource::collection($recent),
            'upcoming_closings' => $upcoming->map(fn (Opportunity $opportunity) => [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'value' => (float) $opportunity->value,
                'expected_close_date' => $opportunity->expected_close_date?->toDateString(),
                'company' => $opportunity->company ? [
                    'id' => $opportunity->company->id,
                    'name' => $opportunity->company->name,
                ] : null,
            ])->values(),
        ];
    }

    public function manager(): array
    {
        $opportunities = Opportunity::query();

        $total = (clone $opportunities)->count();
        $won = (clone $opportunities)->where('status', OpportunityStatus::WON->value);
        $lost = (clone $opportunities)->where('status', OpportunityStatus::LOST->value);

        $wonCount = $won->count();
        $lostCount = $lost->count();
        $closed = $wonCount + $lostCount;

        $team = $this->performanceService->forUsers($this->salesUsers());

        return [
            'total_opportunities' => $total,
            'total_revenue' => round((float) $won->sum('value'), 2),
            'win_rate' => $closed > 0 ? round(($wonCount / $closed) * 100, 2) : 0.0,
            'active_sales_count' => $this->salesUsers()->count(),
            'team_performance' => $team->map(fn (array $row) => [
                'rank' => $row['performance']['rank'],
                'user' => [
                    'id' => $row['user']->id,
                    'name' => $row['user']->name,
                    'email' => $row['user']->email,
                ],
                'total_deals' => $row['performance']['total_deals'],
                'won_deals' => $row['performance']['won_deals'],
                'total_revenue' => $row['performance']['total_revenue'],
                'win_rate' => $row['performance']['win_rate'],
            ])->values(),
            'pipeline_by_status' => $this->pipelineByStatus(),
            'monthly_performance' => $this->monthlyPerformance(6),
            'principal_performance' => $this->principalPerformance(
                (int) config('salestrack.top_principal_count', 8)
            ),
            'top_performer' => $team->isEmpty() ? null : [
                'rank' => $team[0]['performance']['rank'],
                'user' => [
                    'id' => $team[0]['user']->id,
                    'name' => $team[0]['user']->name,
                    'email' => $team[0]['user']->email,
                ],
                'total_revenue' => $team[0]['performance']['total_revenue'],
                'win_rate' => $team[0]['performance']['win_rate'],
            ],
        ];
    }

    public function bod(): array
    {
        $opportunities = Opportunity::query();

        $won = (clone $opportunities)->where('status', OpportunityStatus::WON->value);
        $lost = (clone $opportunities)->where('status', OpportunityStatus::LOST->value);
        $open = (clone $opportunities)->where('status', OpportunityStatus::OPEN->value);

        $wonCount = $won->count();
        $lostCount = $lost->count();
        $closed = $wonCount + $lostCount;

        $forecast = $this->revenueReportService->forecast();

        return [
            'summary' => [
                'total_revenue' => round((float) $won->sum('value'), 2),
                'total_pipeline' => round((float) $open->sum('value'), 2),
                'total_opportunities' => (clone $opportunities)->count(),
                'win_rate' => $closed > 0 ? round(($wonCount / $closed) * 100, 2) : 0.0,
                'active_sales_count' => $this->salesUsers()->count(),
            ],
            'revenue_trend' => $this->revenueTrend(12),
            'forecast_6_months' => $forecast['forecast_6_months'],
            'top_sales' => $this->performanceService->leaderboard(
                (int) config('salestrack.top_sales_count', 5)
            ),
        ];
    }

    private function pipelineByStatus(?int $userId = null): array
    {
        return Opportunity::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(value), 0) as total_value')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->total,
                'value' => round((float) $row->total_value, 2),
            ])
            ->all();
    }

    private function monthlyPerformance(int $months): array
    {
        $keys = [];
        $cursor = now()->startOfMonth();
        for ($i = $months - 1; $i >= 0; $i--) {
            $keys[] = $cursor->copy()->subMonths($i)->format('Y-m');
        }

        $from = Carbon::parse($keys[0])->startOfMonth();

        $opportunities = Opportunity::query()
            ->where(function ($query) use ($from) {
                $query->where('created_at', '>=', $from->toDateTimeString())
                    ->orWhere('status', OpportunityStatus::WON->value);
            })
            ->get(['id', 'status', 'value', 'expected_close_date', 'created_at', 'updated_at']);

        $createdGrouped = $opportunities->groupBy(fn (Opportunity $o) => $o->created_at->format('Y-m'));
        $revenueGrouped = $opportunities
            ->where('status', OpportunityStatus::WON->value)
            ->groupBy(fn (Opportunity $o) => $this->revenueMonth($o));

        return array_map(fn (string $key) => [
            'month' => $key,
            'opportunities' => $createdGrouped->get($key, collect())->count(),
            'revenue' => round((float) $revenueGrouped->get($key, collect())->sum('value'), 2),
        ], $keys);
    }

    private function revenueTrend(int $months): array
    {
        $keys = [];
        $cursor = now()->startOfMonth();
        for ($i = $months - 1; $i >= 0; $i--) {
            $keys[] = $cursor->copy()->subMonths($i)->format('Y-m');
        }

        $won = Opportunity::query()
            ->where('status', OpportunityStatus::WON->value)
            ->get(['id', 'status', 'value', 'expected_close_date', 'updated_at']);

        $grouped = $won->groupBy(fn (Opportunity $o) => $this->revenueMonth($o));

        return array_map(fn (string $key) => [
            'month' => $key,
            'revenue' => round((float) $grouped->get($key, collect())->sum('value'), 2),
            'deals' => $grouped->get($key, collect())->count(),
        ], $keys);
    }

    private function principalPerformance(int $limit): array
    {
        $won = Opportunity::query()
            ->where('status', OpportunityStatus::WON->value)
            ->with('principal:id,name')
            ->get(['id', 'principal_id', 'value']);

        return $won->groupBy('principal_id')
            ->map(function (Collection $rows) {
                $principal = $rows->first()->principal;

                return [
                    'principal' => $principal ? [
                        'id' => $principal->id,
                        'name' => $principal->name,
                    ] : null,
                    'won_deals' => $rows->count(),
                    'revenue' => round((float) $rows->sum('value'), 2),
                ];
            })
            ->filter(fn (array $row) => $row['principal'] !== null)
            ->values()
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->all();
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
