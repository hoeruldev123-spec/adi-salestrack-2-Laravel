<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'user_id', 'principal_id', 'title', 'description', 'value',
    'stage', 'status', 'probability', 'expected_close_date', 'source',
])]
class Opportunity extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(MasterPrincipal::class, 'principal_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OpportunityItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OpportunityLog::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }
}
