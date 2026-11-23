<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'pricing_type', 'unit_price', 'currency', 'billing_period',
    'effective_date', 'expiry_date', 'is_active',
])]
class PriceList extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'effective_date' => 'date',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('effective_date')->orWhereDate('effective_date', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now());
            });
    }
}
