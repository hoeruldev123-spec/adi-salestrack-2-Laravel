<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'forecast_run_id', 'name', 'description', 'win_rate_adjustment',
    'revenue_adjustment', 'opportunity_adjustment', 'target_revenue',
])]
class ForecastScenario extends Model
{
    use HasFactory;

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}
