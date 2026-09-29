<?php

namespace App\Services\Sales;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Product::query()
            ->with('principal')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"));
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['principal_id'] ?? null, fn ($query, $id) => $query->where('principal_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh('principal');
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
