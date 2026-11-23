<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'principal_id', 'category_id', 'name', 'product_type', 'sku', 'type',
    'description', 'unit', 'price', 'status', 'is_active',
])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->isDirty('product_type') && $product->product_type) {
                $product->type = $product->product_type;
            } elseif ($product->isDirty('type') && $product->type) {
                $product->product_type = $product->type;
            } elseif (! $product->exists) {
                $product->product_type = $product->product_type ?: $product->type;
                $product->type = $product->type ?: $product->product_type;
            }

            if ($product->isDirty('is_active')) {
                $product->status = $product->is_active ? 'ACTIVE' : 'INACTIVE';
            } elseif ($product->isDirty('status')) {
                $product->is_active = $product->status === 'ACTIVE';
            } elseif (! $product->exists) {
                $product->is_active = $product->status !== 'INACTIVE';
            }
        });
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(MasterPrincipal::class, 'principal_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }

    public function opportunityItems(): HasMany
    {
        return $this->hasMany(OpportunityItem::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(SalesTransactionItem::class);
    }
}
