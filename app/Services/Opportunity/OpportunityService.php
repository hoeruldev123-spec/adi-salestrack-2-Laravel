<?php

namespace App\Services\Opportunity;

use App\Enums\OpportunityStage;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityLog;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class OpportunityService
{
    public function paginate(array $filters, ?User $authUser = null): LengthAwarePaginator
    {
        $userId = $filters['user_id'] ?? $filters['sales_id'] ?? null;
        $year = $filters['year'] ?? null;
        $category = $filters['category'] ?? $filters['category_id'] ?? null;

        return Opportunity::query()
            ->with(['company', 'user', 'principal'])
            ->withCount(['items', 'activities'])
            ->when($authUser && ! $this->canViewAll($authUser), fn ($query) => $query->where('user_id', $authUser->id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['stage'] ?? null, function ($query, $stage) {
                $query->where(function ($q) use ($stage) {
                    $q->where('stage', $stage)->orWhere('progress', $stage);
                    if (strcasecmp($stage, 'LEAD') === 0 || strcasecmp($stage, 'Introduction') === 0) {
                        $q->orWhere('stage', 'Introduction')->orWhere('stage', 'LEAD');
                    } elseif (strcasecmp($stage, 'WON') === 0 || strcasecmp($stage, 'Closed Won') === 0) {
                        $q->orWhere('stage', 'Closed Won')->orWhere('stage', 'WON');
                    } elseif (strcasecmp($stage, 'LOST') === 0 || strcasecmp($stage, 'Closed Lost') === 0) {
                        $q->orWhere('stage', 'Closed Lost')->orWhere('stage', 'LOST');
                    }
                });
            })
            ->when($filters['progress'] ?? null, fn ($query, $progress) => $query->where('progress', $progress))
            ->when($filters['license_type'] ?? null, fn ($query, $type) => $query->where('license_type', $type))
            ->when($filters['company_id'] ?? null, fn ($query, $id) => $query->where('company_id', $id))
            ->when($filters['principal_id'] ?? null, fn ($query, $id) => $query->where('principal_id', $id))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($year && $year !== 'ALL', function ($query) use ($year) {
                $query->where(function ($q) use ($year) {
                    $q->whereYear('closing_date', $year)
                        ->orWhere(function ($q2) use ($year) {
                            $q2->whereNull('closing_date')->whereYear('expected_close_date', $year);
                        })
                        ->orWhere(function ($q3) use ($year) {
                            $q3->whereNull('closing_date')->whereNull('expected_close_date')->whereYear('created_at', $year);
                        });
                });
            })
            ->when($category && $category !== 'ALL', function ($query) use ($category) {
                if (is_numeric($category)) {
                    $query->whereHas('items.product', fn ($q) => $q->where('category_id', $category));
                } else {
                    $query->where(function ($q) use ($category) {
                        $q->whereHas('items.product', fn ($sub) => $sub->where('type', $category)->orWhere('product_type', $category))
                            ->orWhere('license_type', $category);
                    });
                }
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('project_name', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%");
                });
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

        if (! empty($items)) {
            $itemsTotal = $this->calculateItemsSubtotal($items);
            if (empty($data['value']) && empty($data['estimated_value'])) {
                $data['value'] = $itemsTotal;
            }
        }

        $data = $this->normalizeSpecFields($data);

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

        if ($items !== null && empty($data['value']) && empty($data['estimated_value'])) {
            $data['value'] = $this->calculateItemsSubtotal($items);
        }

        $data = $this->normalizeStageStatus($opportunity, $data);
        $data = $this->normalizeSpecFields($data, $opportunity);

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

    public function addItem(Opportunity $opportunity, array $itemData, User $user): OpportunityItem
    {
        $product = Product::findOrFail($itemData['product_id']);
        $priceList = ! empty($itemData['price_list_id']) ? PriceList::find($itemData['price_list_id']) : null;
        $quantity = $itemData['quantity'] ?? 1;
        $unitPrice = $itemData['unit_price'] ?? $priceList?->unit_price ?? $product->price ?? 0;
        $discount = $itemData['discount'] ?? 0;
        $subtotal = ($unitPrice * $quantity) - $discount;

        $item = OpportunityItem::create([
            'opportunity_id' => $opportunity->id,
            'product_id' => $product->id,
            'price_list_id' => $priceList?->id,
            'item_description' => $itemData['item_description'] ?? $product->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'subtotal' => $subtotal,
        ]);

        // Recalculate opportunity value according to Section 3.7 & 6.3
        $total = $opportunity->items()->sum('subtotal');
        $opportunity->update([
            'value' => $total,
            'estimated_value' => $total,
        ]);

        $this->log($opportunity, $user, null, null, null, 'Opportunity item added: ' . ($item->item_description ?: $product->name));

        return $item->load('product', 'priceList');
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
            $priceList = ! empty($item['price_list_id']) ? PriceList::find($item['price_list_id']) : null;
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? $priceList?->unit_price ?? $product->price;
            $discount = $item['discount'] ?? 0;
            $subtotal = ($unitPrice * $quantity) - $discount;

            OpportunityItem::create([
                'opportunity_id' => $opportunity->id,
                'product_id' => $product->id,
                'price_list_id' => $priceList?->id,
                'item_description' => $item['item_description'] ?? $product->name,
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
            $priceList = ! empty($item['price_list_id']) ? PriceList::find($item['price_list_id']) : null;
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? $priceList?->unit_price ?? $product->price;
            $discount = $item['discount'] ?? 0;
            $total += ($unitPrice * $quantity) - $discount;
        }

        return $total;
    }

    private function normalizeSpecFields(array $data, ?Opportunity $opportunity = null): array
    {
        $title = $data['title'] ?? $opportunity?->title;
        $projectName = $data['project_name'] ?? $opportunity?->project_name;
        $value = $data['value'] ?? $opportunity?->value;
        $estimatedValue = $data['estimated_value'] ?? $opportunity?->estimated_value;
        $stage = $data['stage'] ?? $opportunity?->stage;
        $progress = $data['progress'] ?? $opportunity?->progress;

        $data['title'] = $title ?: $projectName;
        $data['project_name'] = $projectName ?: $title;
        $data['value'] = $value ?? $estimatedValue ?? 0;
        $data['estimated_value'] = $estimatedValue ?? $value ?? 0;
        $data['progress'] = $progress ?: $stage;
        $data['client_name'] = $data['client_name']
            ?? $opportunity?->client_name
            ?? $opportunity?->company?->name;

        return $data;
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

        if (isset($data['stage']) && ! isset($data['progress'])) {
            $data['progress'] = $data['stage'];
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
            'company_id', 'user_id', 'principal_id', 'title', 'project_name', 'client_name',
            'value', 'estimated_value', 'stage', 'progress', 'status', 'probability',
            'sa_pic', 'license_type', 'deal_registration', 'expected_close_date',
            'actual_closing_date', 'source', 'remarks',
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
