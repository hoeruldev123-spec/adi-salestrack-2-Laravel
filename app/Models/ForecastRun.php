<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name', 'forecast_type', 'period_start', 'period_end', 'simulation_count',
    'confidence_level', 'target_revenue', 'status', 'started_at', 'completed_at', 'created_by',
])]
class ForecastRun extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'simulation_count' => 'integer',
            'confidence_level' => 'integer',
            'target_revenue' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function result(): HasOne
    {
        return $this->hasOne(ForecastResult::class);
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(ForecastScenario::class);
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(ForecastSimulation::class);
    }
}
