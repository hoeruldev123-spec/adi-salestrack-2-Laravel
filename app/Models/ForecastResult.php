<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'forecast_run_id', 'opportunity_id', 'simulated_value', 'probability',
    'simulation_iteration', 'random_number', 'is_success', 'minimum', 'maximum',
    'mean', 'median', 'p10', 'p25', 'p50', 'p75', 'p90', 'p95', 'target_probability',
])]
class ForecastResult extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'simulated_value' => 'decimal:2',
            'probability' => 'decimal:2',
            'simulation_iteration' => 'integer',
            'random_number' => 'decimal:8',
            'is_success' => 'boolean',
            'minimum' => 'decimal:2',
            'maximum' => 'decimal:2',
            'mean' => 'decimal:2',
            'median' => 'decimal:2',
            'p10' => 'decimal:2',
            'p25' => 'decimal:2',
            'p50' => 'decimal:2',
            'p75' => 'decimal:2',
            'p90' => 'decimal:2',
            'p95' => 'decimal:2',
            'target_probability' => 'decimal:2',
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
