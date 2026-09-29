<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'opportunity_id', 'company_id', 'user_id', 'transaction_date',
    'transaction_number', 'total_amount', 'status',
])]
class SalesTransaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesTransactionItem::class);
    }
}
