<?php

namespace App\Services\Analytics;

use App\Enums\OpportunityStatus;
use App\Enums\TransactionStatus;
use App\Models\Opportunity;
use App\Models\SalesTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function dashboard(?string $year = null, ?User $authUser = null): array
    {
        $year = $year ?: 'ALL';
        $isAll = strtoupper((string) $year) === 'ALL';
        $from = $isAll ? null : "{$year}-01-01";
        $to = $isAll ? null : "{$year}-12-31";

        $isSales = $authUser && ($authUser->role?->name === 'SALES' || $authUser->role_id === 2);

        $paidTransactions = SalesTransaction::query()
            ->where('status', TransactionStatus::PAID->value)
            ->when(! $isAll, fn ($q) => $q->whereBetween('transaction_date', [$from, $to]))
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));

        $totalRevenue = (float) (clone $paidTransactions)->sum('total_amount');

        $openOpportunities = Opportunity::where('status', OpportunityStatus::OPEN->value)
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));
        $totalPipeline = (float) (clone $openOpportunities)->sum('value');

        $wonOpportunities = Opportunity::where('status', OpportunityStatus::WON->value)
            ->when(! $isAll, function ($q) use ($year) {
                $q->where(function ($sub) use ($year) {
                    $sub->whereYear('closing_date', $year)
                        ->orWhere(fn ($q2) => $q2->whereNull('closing_date')->whereYear('expected_close_date', $year));
                });
            })
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));
        $wonRevenue = (float) (clone $wonOpportunities)->sum('value');

        $totalClosed = Opportunity::whereIn('status', [
            OpportunityStatus::WON->value,
            OpportunityStatus::LOST->value,
        ])
            ->when(! $isAll, function ($q) use ($year) {
                $q->where(function ($sub) use ($year) {
                    $sub->whereYear('closing_date', $year)
                        ->orWhere(fn ($q2) => $q2->whereNull('closing_date')->whereYear('expected_close_date', $year));
                });
            })
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id))
            ->count();
        $wonCount = (clone $wonOpportunities)->count();
        $winRate = $totalClosed > 0 ? round(($wonCount / $totalClosed) * 100, 2) : 0;

        $wonTransactions = (clone $paidTransactions)->count();
        $averageDealSize = $wonTransactions > 0
            ? round($totalRevenue / $wonTransactions, 2)
            : 0;

        $latestForecast = \App\Models\ForecastRun::where('status', 'COMPLETED')
            ->whereHas('result', fn ($q) => $q->where('mean', '>', 0))
            ->latest('id')
            ->first();
        $forecastRevenue = $latestForecast?->result?->mean ? (float) $latestForecast->result->mean : null;
        $targetProbability = $latestForecast?->result?->target_probability ? (float) $latestForecast->result->target_probability : null;

        return [
            'year' => $year,
            'total_revenue' => $totalRevenue,
            'total_pipeline' => $totalPipeline,
            'won_revenue' => $wonRevenue,
            'win_rate' => $winRate,
            'average_deal_size' => $averageDealSize,
            'open_opportunities' => (clone $openOpportunities)->count(),
            'won_opportunities' => $wonCount,
            'transaction_count' => $wonTransactions,
            'forecast_revenue' => $forecastRevenue,
            'target_probability' => $targetProbability,
        ];
    }

    public function revenue(array $filters = [], ?User $authUser = null): array
    {
        $year = $filters['year'] ?? 'ALL';
        $isAll = strtoupper((string) $year) === 'ALL';
        $from = $filters['from'] ?? ($isAll ? null : "{$year}-01-01");
        $to = $filters['to'] ?? ($isAll ? null : "{$year}-12-31");

        $isSales = $authUser && ($authUser->role?->name === 'SALES' || $authUser->role_id === 2);

        $base = SalesTransaction::query()
            ->where('status', TransactionStatus::PAID->value)
            ->when(! $isAll && $from && $to, fn ($q) => $q->whereBetween('transaction_date', [$from, $to]))
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));

        $periods = [];
        if ($isAll) {
            $monthly = (clone $base)
                ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as period, SUM(total_amount) as total')
                ->groupBy('period')
                ->orderBy('period')
                ->pluck('total', 'period')
                ->map(fn ($v) => (float) $v);

            $earliest = (clone $base)->min('transaction_date') ?: '2020-01-01';
            $latest = (clone $base)->max('transaction_date') ?: '2025-12-31';
            $cursor = Carbon::parse($earliest)->startOfMonth();
            $end = Carbon::parse($latest)->startOfMonth();
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m');
                $periods[$key] = $monthly[$key] ?? 0.0;
                $cursor->addMonth();
            }
            $from = Carbon::parse($earliest)->toDateString();
            $to = Carbon::parse($latest)->toDateString();
        } else {
            $monthly = (clone $base)
                ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as period, SUM(total_amount) as total')
                ->groupBy('period')
                ->orderBy('period')
                ->pluck('total', 'period')
                ->map(fn ($v) => (float) $v);

            $cursor = Carbon::parse($from)->startOfMonth();
            $end = Carbon::parse($to)->startOfMonth();
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m');
                $periods[$key] = $monthly[$key] ?? 0.0;
                $cursor->addMonth();
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'total' => (float) (clone $base)->sum('total_amount'),
            'monthly' => $periods,
            'by_principal' => $this->revenueByPrincipal($isAll ? null : $from, $isAll ? null : $to, $isSales ? $authUser->id : null),
            'by_customer' => $this->revenueByCustomer($base),
            'by_product' => $this->revenueByProduct($isAll ? null : $from, $isAll ? null : $to, $isSales ? $authUser->id : null),
            'by_salesperson' => $this->revenueBySalesperson($base),
        ];
    }

    public function pipeline(?User $authUser = null, ?string $year = null): array
    {
        $isSales = $authUser && ($authUser->role?->name === 'SALES' || $authUser->role_id === 2);
        $isAll = ! $year || strtoupper((string) $year) === 'ALL';

        $open = Opportunity::where('status', OpportunityStatus::OPEN->value)
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));
        $won = Opportunity::where('status', OpportunityStatus::WON->value)
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));
        $lost = Opportunity::where('status', OpportunityStatus::LOST->value)
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));
        $cancelled = Opportunity::where('status', OpportunityStatus::CANCELLED->value)
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));
        $onHold = Opportunity::where('status', OpportunityStatus::ON_HOLD->value)
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));

        if (! $isAll) {
            $yearFilter = function ($q) use ($year) {
                $q->where(function ($sub) use ($year) {
                    $sub->whereYear('closing_date', $year)
                        ->orWhere(fn ($q2) => $q2->whereNull('closing_date')->whereYear('expected_close_date', $year))
                        ->orWhere(fn ($q3) => $q3->whereNull('closing_date')->whereNull('expected_close_date')->whereYear('created_at', $year));
                });
            };
            $open->where($yearFilter);
            $won->where($yearFilter);
            $lost->where($yearFilter);
            $cancelled->where($yearFilter);
            $onHold->where($yearFilter);
        }

        $totalClosed = $won->count() + $lost->count();

        return [
            'total_pipeline' => (float) $open->sum('value'),
            'open' => $open->count(),
            'open_value' => (float) (clone $open)->sum('value'),
            'won' => $won->count(),
            'won_value' => (float) (clone $won)->sum('value'),
            'lost' => $lost->count(),
            'lost_value' => (float) (clone $lost)->sum('value'),
            'cancelled' => $cancelled->count(),
            'on_hold' => $onHold->count(),
            'win_rate' => $totalClosed > 0 ? round(($won->count() / $totalClosed) * 100, 2) : 0,
        ];
    }

    public function performance(array $filters = [], ?User $authUser = null): array
    {
        $year = $filters['year'] ?? 'ALL';
        $isAll = strtoupper((string) $year) === 'ALL';
        $from = $filters['from'] ?? ($isAll ? null : "{$year}-01-01");
        $to = $filters['to'] ?? ($isAll ? null : "{$year}-12-31");

        $isSales = $authUser && ($authUser->role?->name === 'SALES' || $authUser->role_id === 2);

        $transactions = SalesTransaction::query()
            ->where('status', TransactionStatus::PAID->value)
            ->when(! $isAll && $from && $to, fn ($q) => $q->whereBetween('transaction_date', [$from, $to]))
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id));

        $oppsByUser = Opportunity::query()
            ->when(! $isAll, function ($q) use ($year) {
                $q->where(function ($sub) use ($year) {
                    $sub->whereYear('closing_date', $year)
                        ->orWhere(fn ($q2) => $q2->whereNull('closing_date')->whereYear('expected_close_date', $year));
                });
            })
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id))
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total_opps'),
                DB::raw('SUM(CASE WHEN status = "WON" THEN 1 ELSE 0 END) as won_opps')
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $bySalesperson = (clone $transactions)
            ->select('user_id', DB::raw('COUNT(*) as deals'), DB::raw('SUM(total_amount) as revenue'))
            ->with('user:id,name')
            ->groupBy('user_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($row) use ($oppsByUser) {
                $oppStats = $oppsByUser->get($row->user_id);
                $oppCount = $oppStats ? (int) $oppStats->total_opps : (int) $row->deals;
                $wonCount = $oppStats ? (int) $oppStats->won_opps : (int) $row->deals;
                $winRate = $oppCount > 0 ? round(($wonCount / $oppCount) * 100, 2) : 100.0;

                return [
                    'user' => $row->user ? ['id' => $row->user->id, 'name' => $row->user->name] : null,
                    'deals' => $row->deals,
                    'opportunities_count' => $oppCount,
                    'win_count' => $wonCount,
                    'win_rate' => $winRate,
                    'revenue' => (float) $row->revenue,
                    'average_deal_size' => $row->deals > 0 ? round($row->revenue / $row->deals, 2) : 0,
                ];
            })->values();

        $byProduct = DB::table('sales_transaction_items')
            ->join('sales_transactions', 'sales_transactions.id', '=', 'sales_transaction_items.sales_transaction_id')
            ->join('products', 'products.id', '=', 'sales_transaction_items.product_id')
            ->where('sales_transactions.status', TransactionStatus::PAID->value)
            ->when(! $isAll && $from && $to, fn ($q) => $q->whereBetween('sales_transactions.transaction_date', [$from, $to]))
            ->when($isSales, fn ($q) => $q->where('sales_transactions.user_id', $authUser->id))
            ->select('products.id', 'products.name', DB::raw('SUM(sales_transaction_items.subtotal) as revenue'), DB::raw('SUM(sales_transaction_items.quantity) as quantity'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'quantity' => $row->quantity,
                'revenue' => (float) $row->revenue,
            ]);

        $closedOpportunities = Opportunity::whereIn('status', [
            OpportunityStatus::WON->value,
            OpportunityStatus::LOST->value,
        ])
            ->when(! $isAll, function ($q) use ($year) {
                $q->where(function ($sub) use ($year) {
                    $sub->whereYear('closing_date', $year)
                        ->orWhere(fn ($q2) => $q2->whereNull('closing_date')->whereYear('expected_close_date', $year));
                });
            })
            ->when($isSales, fn ($q) => $q->where('user_id', $authUser->id))
            ->get(['created_at', 'updated_at']);

        $totalDays = 0;
        $closedCount = 0;
        foreach ($closedOpportunities as $opportunity) {
            $cycle = $opportunity->created_at->diffInDays($opportunity->updated_at);
            $totalDays += $cycle;
            $closedCount++;
        }

        return [
            'by_salesperson' => $bySalesperson,
            'by_product' => $byProduct,
            'average_deal_size' => $this->averageDealSize($isAll ? null : $from, $isAll ? null : $to, $isSales ? $authUser->id : null),
            'average_sales_cycle_days' => $closedCount > 0 ? round($totalDays / $closedCount, 1) : 0,
        ];
    }

    private function revenueByPrincipal(?string $from = null, ?string $to = null, ?int $salesUserId = null)
    {
        return DB::table('sales_transaction_items')
            ->join('sales_transactions', 'sales_transactions.id', '=', 'sales_transaction_items.sales_transaction_id')
            ->join('products', 'products.id', '=', 'sales_transaction_items.product_id')
            ->join('principals', 'principals.id', '=', 'products.principal_id')
            ->where('sales_transactions.status', TransactionStatus::PAID->value)
            ->when($from && $to, fn ($q) => $q->whereBetween('sales_transactions.transaction_date', [$from, $to]))
            ->when($salesUserId, fn ($q) => $q->where('sales_transactions.user_id', $salesUserId))
            ->select('principals.id', 'principals.name', DB::raw('SUM(sales_transaction_items.subtotal) as revenue'))
            ->groupBy('principals.id', 'principals.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'revenue' => (float) $row->revenue,
            ]);
    }

    private function revenueByCustomer($base)
    {
        return (clone $base)
            ->select('company_id', DB::raw('SUM(total_amount) as revenue'))
            ->with('company:id,name')
            ->groupBy('company_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'company' => $row->company ? ['id' => $row->company->id, 'name' => $row->company->name] : null,
                'revenue' => (float) $row->revenue,
            ])
            ->values();
    }

    private function revenueByProduct(?string $from = null, ?string $to = null, ?int $salesUserId = null)
    {
        return DB::table('sales_transaction_items')
            ->join('sales_transactions', 'sales_transactions.id', '=', 'sales_transaction_items.sales_transaction_id')
            ->join('products', 'products.id', '=', 'sales_transaction_items.product_id')
            ->where('sales_transactions.status', TransactionStatus::PAID->value)
            ->when($from && $to, fn ($q) => $q->whereBetween('sales_transactions.transaction_date', [$from, $to]))
            ->when($salesUserId, fn ($q) => $q->where('sales_transactions.user_id', $salesUserId))
            ->select('products.id', 'products.name', DB::raw('SUM(sales_transaction_items.subtotal) as revenue'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'revenue' => (float) $row->revenue,
            ]);
    }

    private function revenueBySalesperson($base)
    {
        return (clone $base)
            ->select('user_id', DB::raw('SUM(total_amount) as revenue'))
            ->with('user:id,name')
            ->groupBy('user_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'user' => $row->user ? ['id' => $row->user->id, 'name' => $row->user->name] : null,
                'revenue' => (float) $row->revenue,
            ])
            ->values();
    }

    private function averageDealSize(?string $from = null, ?string $to = null, ?int $salesUserId = null): float
    {
        $paid = SalesTransaction::where('status', TransactionStatus::PAID->value)
            ->when($from && $to, fn ($q) => $q->whereBetween('transaction_date', [$from, $to]))
            ->when($salesUserId, fn ($q) => $q->where('user_id', $salesUserId));

        $count = (clone $paid)->count();

        return $count > 0 ? round((clone $paid)->sum('total_amount') / $count, 2) : 0;
    }
}
