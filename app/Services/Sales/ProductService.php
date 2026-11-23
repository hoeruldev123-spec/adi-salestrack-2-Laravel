<?php

namespace App\Services\Sales;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Product::query()
            ->with(['principal', 'category'])
            ->withCount(['specifications', 'priceLists'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"));
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['product_type'] ?? null, fn ($query, $type) => $query->where('product_type', $type))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['principal_id'] ?? null, fn ($query, $id) => $query->where('principal_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
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

        return $product->fresh(['principal', 'category']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
