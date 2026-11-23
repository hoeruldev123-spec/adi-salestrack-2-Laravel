<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'opportunity_id',
    'previous_stage',
    'current_stage',
    'previous_probability',
    'current_probability',
    'changed_at',
])]
class OpportunityStageHistory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'previous_probability' => 'decimal:2',
            'current_probability' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
