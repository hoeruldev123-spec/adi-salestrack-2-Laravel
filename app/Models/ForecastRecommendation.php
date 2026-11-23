<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'forecast_run_id',
    'opportunity_id',
    'recommendation_type',
    'priority',
    'recommendation_title',
    'recommendation_description',
    'estimated_value',
    'probability',
    'action_suggestion',
])]
class ForecastRecommendation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'probability' => 'decimal:2',
        ];
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
