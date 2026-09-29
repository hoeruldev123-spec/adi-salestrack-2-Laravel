<?php

namespace App\Services\Opportunity;

use App\Enums\OpportunityStage;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class OpportunityService
{
    public function paginate(array $filters, ?User $authUser = null): LengthAwarePaginator
    {
        return Opportunity::query()
            ->with(['company', 'user', 'principal'])
            ->withCount(['items', 'activities'])
            ->when($authUser && ! $this->canViewAll($authUser), fn ($query) => $query->where('user_id', $authUser->id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['stage'] ?? null, fn ($query, $stage) => $query->where('stage', $stage))
            ->when($filters['company_id'] ?? null, fn ($query, $id) => $query->where('company_id', $id))
            ->when($filters['principal_id'] ?? null, fn ($query, $id) => $query->where('principal_id', $id))
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data, User $user): Opportunity
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['user_id'] = $data['user_id'] ?? $user->id;

        if (! isset($data['probability'])) {
            $data['probability'] = $this->defaultProbability($data['status'] ?? null, $data['stage'] ?? null);
        }

        if (empty($data['value']) && ! empty($items)) {
            $data['value'] = $this->calculateItemsSubtotal($items);
        }

        $opportunity = Opportunity::create($data);

        if (! empty($items)) {
            $this->syncItems($opportunity, $items);
        }

        $this->log($opportunity, $user, null, null, null, 'Opportunity created');

        return $opportunity->fresh(['company', 'user', 'principal', 'items.product']);
    }

    public function update(Opportunity $opportunity, array $data, User $user): Opportunity
    {
        $items = $data['items'] ?? null;
        unset($data['items']);

        if ($items !== null && empty($data['value'])) {
            $data['value'] = $this->calculateItemsSubtotal($items);
        }

        $data = $this->normalizeStageStatus($opportunity, $data);

        $changed = $this->changedFields($opportunity, $data);

        $opportunity->update($data);

        if ($items !== null) {
            $this->syncItems($opportunity, $items);
            $this->log($opportunity, $user, null, null, null, 'Opportunity items updated');
        }

        foreach ($changed as $field => $values) {
            $this->log($opportunity, $user, $field, $values['old'], $values['new'], null);
        }

        return $opportunity->fresh(['company', 'user', 'principal', 'items.product']);
    }

    public function destroy(Opportunity $opportunity): void
    {
        $opportunity->delete();
    }

    public function logs(Opportunity $opportunity)
    {
        return $opportunity->logs()
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(request('per_page', 15));
    }

    public function log(
        Opportunity $opportunity,
        User $user,
        ?string $field,
        ?string $old,
        ?string $new,
        ?string $description
    ): void {
        OpportunityLog::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'field_name' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'description' => $description,
        ]);
    }

    private function syncItems(Opportunity $opportunity, array $items): void
    {
        $opportunity->items()->delete();

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? $product->price;
            $discount = $item['discount'] ?? 0;
            $subtotal = ($unitPrice * $quantity) - $discount;

            OpportunityItem::create([
                'opportunity_id' => $opportunity->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'subtotal' => $subtotal,
            ]);
        }
    }

    private function calculateItemsSubtotal(array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? $product->price;
            $discount = $item['discount'] ?? 0;
            $total += ($unitPrice * $quantity) - $discount;
        }

        return $total;
    }

    private function normalizeStageStatus(Opportunity $opportunity, array $data): array
    {
        $newStage = $data['stage'] ?? $opportunity->stage;
        $newStatus = $data['status'] ?? $opportunity->status;

        if (! isset($data['probability'])) {
            if ($newStatus === OpportunityStatus::WON->value) {
                $data['probability'] = 100;
            } elseif ($newStatus === OpportunityStatus::LOST->value) {
                $data['probability'] = 0;
            } elseif ($newStage !== $opportunity->stage) {
                $stageEnum = OpportunityStage::tryFrom($newStage);
                $data['probability'] = $stageEnum?->defaultProbability() ?? $opportunity->probability;
            }
        }

        return $data;
    }

    private function defaultProbability(?string $status, ?string $stage): int
    {
        if ($status === OpportunityStatus::WON->value) {
            return 100;
        }

        if ($status === OpportunityStatus::LOST->value) {
            return 0;
        }

        $stageEnum = OpportunityStage::tryFrom($stage ?? '');

        return $stageEnum?->defaultProbability() ?? 10;
    }

    private function changedFields(Opportunity $opportunity, array $data): array
    {
        $tracked = [
            'company_id', 'user_id', 'principal_id', 'title', 'value', 'stage',
            'status', 'probability', 'expected_close_date', 'source',
        ];

        $changed = [];

        foreach ($tracked as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $old = (string) $opportunity->getRawOriginal($field);
            $new = (string) $data[$field];

            if ($old !== $new) {
                $changed[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changed;
    }

    private function canViewAll(User $user): bool
    {
        return in_array($user->role?->name, ['ADMIN', 'SALES_MANAGER', 'MANAGEMENT'], true);
    }
}
