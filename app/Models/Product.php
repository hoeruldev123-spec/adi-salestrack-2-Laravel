<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['principal_id', 'name', 'sku', 'type', 'description', 'price', 'status'])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(MasterPrincipal::class, 'principal_id');
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
