<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['forecast_run_id', 'simulation_number', 'total_revenue', 'target_achieved'])]
class ForecastSimulation extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'simulation_number' => 'integer',
            'total_revenue' => 'decimal:2',
            'target_achieved' => 'boolean',
        ];
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}
