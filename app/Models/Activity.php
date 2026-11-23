<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'opportunity_id', 'user_id', 'type', 'activity_type', 'subject', 'description',
    'activity_date', 'due_date', 'status',
])]
class Activity extends Model
{
    use HasFactory;

    protected $table = 'sales_activities';

    protected function casts(): array
    {
        return [
            'activity_date' => 'datetime',
            'due_date' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Activity $activity) {
            if ($activity->isDirty('activity_type') && $activity->activity_type) {
                $activity->type = $activity->activity_type;
            } elseif ($activity->isDirty('type') && $activity->type) {
                $activity->activity_type = $activity->type;
            } elseif (! $activity->exists) {
                $activity->activity_type = $activity->activity_type ?: $activity->type;
                $activity->type = $activity->type ?: $activity->activity_type;
            }
        });
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
