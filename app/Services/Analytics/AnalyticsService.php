<?php

namespace App\Services\Analytics;

use App\Enums\OpportunityStatus;
use App\Enums\TransactionStatus;
use App\Models\Opportunity;
use App\Models\SalesTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function dashboard(?string $year = null): array
    {
        $year = $year ?? (string) now()->year;
        $from = "{$year}-01-01";
        $to = "{$year}-12-31";

        $paidTransactions = SalesTransaction::query()
            ->where('status', TransactionStatus::PAID->value)
            ->whereBetween('transaction_date', [$from, $to]);

        $totalRevenue = (float) (clone $paidTransactions)->sum('total_amount');

        $openOpportunities = Opportunity::where('status', OpportunityStatus::OPEN->value);
        $totalPipeline = (float) (clone $openOpportunities)->sum('value');

        $wonOpportunities = Opportunity::where('status', OpportunityStatus::WON->value);
        $wonRevenue = (float) (clone $wonOpportunities)->sum('value');

        $totalClosed = Opportunity::whereIn('status', [
            OpportunityStatus::WON->value,
            OpportunityStatus::LOST->value,
        ])->count();
        $wonCount = (clone $wonOpportunities)->count();
        $winRate = $totalClosed > 0 ? round(($wonCount / $totalClosed) * 100, 2) : 0;

        $wonTransactions = (clone $paidTransactions)->count();
        $averageDealSize = $wonTransactions > 0
            ? round($totalRevenue / $wonTransactions, 2)
            : 0;

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
        ];
    }

    public function revenue(array $filters = []): array
    {
        $year = $filters['year'] ?? (string) now()->year;
        $from = $filters['from'] ?? "{$year}-01-01";
        $to = $filters['to'] ?? "{$year}-12-31";

        $base = SalesTransaction::query()
            ->where('status', TransactionStatus::PAID->value)
            ->whereBetween('transaction_date', [$from, $to]);

        $monthly = (clone $base)
            ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as period, SUM(total_amount) as total')
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('total', 'period')
            ->map(fn ($v) => (float) $v);

        $periods = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->startOfMonth();
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $periods[$key] = $monthly[$key] ?? 0.0;
            $cursor->addMonth();
        }

        return [
            'from' => $from,
            'to' => $to,
            'total' => (float) (clone $base)->sum('total_amount'),
            'monthly' => $periods,
            'by_principal' => $this->revenueByPrincipal($from, $to),
            'by_customer' => $this->revenueByCustomer($base),
            'by_product' => $this->revenueByProduct($from, $to),
            'by_salesperson' => $this->revenueBySalesperson($base),
        ];
    }

    public function pipeline(): array
    {
        $open = Opportunity::where('status', OpportunityStatus::OPEN->value);
        $won = Opportunity::where('status', OpportunityStatus::WON->value);
        $lost = Opportunity::where('status', OpportunityStatus::LOST->value);
        $cancelled = Opportunity::where('status', OpportunityStatus::CANCELLED->value);
        $onHold = Opportunity::where('status', OpportunityStatus::ON_HOLD->value);

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

    public function performance(array $filters = []): array
    {
        $year = $filters['year'] ?? (string) now()->year;
        $from = $filters['from'] ?? "{$year}-01-01";
        $to = $filters['to'] ?? "{$year}-12-31";

        $transactions = SalesTransaction::query()
            ->where('status', TransactionStatus::PAID->value)
            ->whereBetween('transaction_date', [$from, $to]);

        $bySalesperson = (clone $transactions)
            ->select('user_id', DB::raw('COUNT(*) as deals'), DB::raw('SUM(total_amount) as revenue'))
            ->with('user:id,name')
            ->groupBy('user_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'user' => $row->user ? ['id' => $row->user->id, 'name' => $row->user->name] : null,
                'deals' => $row->deals,
                'revenue' => (float) $row->revenue,
                'average_deal_size' => $row->deals > 0 ? round($row->revenue / $row->deals, 2) : 0,
            ])->values();

        $byProduct = DB::table('sales_transaction_items')
            ->join('sales_transactions', 'sales_transactions.id', '=', 'sales_transaction_items.sales_transaction_id')
            ->join('products', 'products.id', '=', 'sales_transaction_items.product_id')
            ->where('sales_transactions.status', TransactionStatus::PAID->value)
            ->whereBetween('sales_transactions.transaction_date', [$from, $to])
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
        ])->get(['created_at', 'updated_at']);

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
            'average_deal_size' => $this->averageDealSize($from, $to),
            'average_sales_cycle_days' => $closedCount > 0 ? round($totalDays / $closedCount, 1) : 0,
        ];
    }

    private function revenueByPrincipal(string $from, string $to)
    {
        return DB::table('sales_transaction_items')
            ->join('sales_transactions', 'sales_transactions.id', '=', 'sales_transaction_items.sales_transaction_id')
            ->join('products', 'products.id', '=', 'sales_transaction_items.product_id')
            ->join('master_principals', 'master_principals.id', '=', 'products.principal_id')
            ->where('sales_transactions.status', TransactionStatus::PAID->value)
            ->whereBetween('sales_transactions.transaction_date', [$from, $to])
            ->select('master_principals.id', 'master_principals.name', DB::raw('SUM(sales_transaction_items.subtotal) as revenue'))
            ->groupBy('master_principals.id', 'master_principals.name')
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

    private function revenueByProduct(string $from, string $to)
    {
        return DB::table('sales_transaction_items')
            ->join('sales_transactions', 'sales_transactions.id', '=', 'sales_transaction_items.sales_transaction_id')
            ->join('products', 'products.id', '=', 'sales_transaction_items.product_id')
            ->where('sales_transactions.status', TransactionStatus::PAID->value)
            ->whereBetween('sales_transactions.transaction_date', [$from, $to])
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

    private function averageDealSize(string $from, string $to): float
    {
        $paid = SalesTransaction::where('status', TransactionStatus::PAID->value)
            ->whereBetween('transaction_date', [$from, $to]);

        $count = (clone $paid)->count();

        return $count > 0 ? round((clone $paid)->sum('total_amount') / $count, 2) : 0;
    }
}
