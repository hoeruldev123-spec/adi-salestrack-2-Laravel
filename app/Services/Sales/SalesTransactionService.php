<?php

namespace App\Services\Sales;

use App\Models\Opportunity;
use App\Models\OpportunityLog;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class SalesTransactionService
{
    public function paginate(array $filters, ?User $authUser = null): LengthAwarePaginator
    {
        return SalesTransaction::query()
            ->with(['opportunity', 'company', 'user'])
            ->withCount('items')
            ->when($authUser && ! $this->canViewAll($authUser), fn ($query) => $query->where('user_id', $authUser->id))
            ->when($filters['company_id'] ?? null, fn ($query, $id) => $query->where('company_id', $id))
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('transaction_date', '<=', $date))
            ->orderByDesc('transaction_date')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data, User $user): SalesTransaction
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['user_id'] = $data['user_id'] ?? $user->id;
        $data['transaction_number'] = $this->generateTransactionNumber();
        $data['total_amount'] = $this->calculateTotal($items);
        $data['status'] = $data['status'] ?? 'PAID';

        $transaction = SalesTransaction::create($data);

        $this->syncItems($transaction, $items);

        if ($transaction->opportunity_id && $transaction->status === 'PAID') {
            $this->markOpportunityWon($transaction->opportunity_id, $user);
        }

        return $transaction->fresh(['opportunity', 'company', 'user', 'items.product']);
    }

    public function update(SalesTransaction $transaction, array $data, User $user): SalesTransaction
    {
        $items = $data['items'] ?? null;
        unset($data['items']);

        if ($items !== null) {
            $data['total_amount'] = $this->calculateTotal($items);
        }

        $transaction->update($data);

        if ($items !== null) {
            $this->syncItems($transaction, $items);
        }

        if ($transaction->status === 'PAID' && $transaction->opportunity_id) {
            $this->markOpportunityWon($transaction->opportunity_id, $user);
        }

        return $transaction->fresh(['opportunity', 'company', 'user', 'items.product']);
    }

    public function destroy(SalesTransaction $transaction): void
    {
        $transaction->delete();
    }

    private function syncItems(SalesTransaction $transaction, array $items): void
    {
        $transaction->items()->delete();

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? $product->price;

            SalesTransactionItem::create([
                'sales_transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice * $quantity,
            ]);
        }
    }

    private function calculateTotal(array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? $product->price;
            $total += $unitPrice * $quantity;
        }

        return $total;
    }

    private function markOpportunityWon(int $opportunityId, User $user): void
    {
        $opportunity = Opportunity::find($opportunityId);

        if ($opportunity && $opportunity->status !== 'WON') {
            $opportunity->update([
                'status' => 'WON',
                'stage' => 'CLOSING',
                'probability' => 100,
            ]);

            OpportunityLog::create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $user->id,
                'field_name' => 'status',
                'old_value' => $opportunity->getRawOriginal('status'),
                'new_value' => 'WON',
                'description' => 'Marked as won via paid sales transaction',
            ]);
        }
    }

    private function generateTransactionNumber(): string
    {
        return 'STX-'.date('Ymd').'-'.strtoupper(uniqid());
    }

    private function canViewAll(User $user): bool
    {
        return in_array($user->role?->name, ['ADMIN', 'SALES_MANAGER', 'MANAGEMENT'], true);
    }
}
