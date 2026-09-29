<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'opportunity_id', 'user_id', 'type', 'subject', 'description',
    'activity_date', 'due_date', 'status',
])]
class Activity extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activity_date' => 'datetime',
            'due_date' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
