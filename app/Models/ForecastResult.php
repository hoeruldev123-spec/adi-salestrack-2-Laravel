<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'forecast_run_id', 'minimum', 'maximum', 'mean', 'median', 'p10', 'p25',
    'p50', 'p75', 'p90', 'p95', 'target_probability',
])]
class ForecastResult extends Model
{
    use HasFactory;

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}
