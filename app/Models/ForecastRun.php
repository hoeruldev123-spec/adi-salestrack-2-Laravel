<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id', 'name', 'forecast_name', 'forecast_type', 'period_start', 'period_end',
    'start_period', 'end_period', 'historical_start_date', 'historical_end_date',
    'forecast_start_date', 'forecast_end_date', 'revenue_category', 'principal_id',
    'forecast_scope', 'simulation_count', 'confidence_level', 'target_revenue',
    'predicted_revenue', 'best_case', 'worst_case', 'most_likely', 'actual_revenue',
    'mape', 'warnings', 'recommendation_thresholds', 'status', 'started_at',
    'completed_at', 'created_by',
])]
class ForecastRun extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'start_period' => 'date',
            'end_period' => 'date',
            'historical_start_date' => 'date',
            'historical_end_date' => 'date',
            'forecast_start_date' => 'date',
            'forecast_end_date' => 'date',
            'simulation_count' => 'integer',
            'confidence_level' => 'integer',
            'target_revenue' => 'decimal:2',
            'predicted_revenue' => 'decimal:2',
            'best_case' => 'decimal:2',
            'worst_case' => 'decimal:2',
            'most_likely' => 'decimal:2',
            'actual_revenue' => 'decimal:2',
            'mape' => 'decimal:4',
            'warnings' => 'array',
            'recommendation_thresholds' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ForecastRun $run) {
            if ($run->isDirty('forecast_name') && $run->forecast_name) {
                $run->name = $run->forecast_name;
            } elseif ($run->isDirty('name') && $run->name) {
                $run->forecast_name = $run->name;
            } elseif (! $run->exists) {
                $run->forecast_name = $run->forecast_name ?: $run->name;
                $run->name = $run->name ?: $run->forecast_name;
            }

            if ($run->start_period && ! $run->period_start) {
                $run->period_start = $run->start_period;
            } elseif ($run->period_start && ! $run->start_period) {
                $run->start_period = $run->period_start;
            }

            if ($run->end_period && ! $run->period_end) {
                $run->period_end = $run->end_period;
            } elseif ($run->period_end && ! $run->end_period) {
                $run->end_period = $run->period_end;
            }

            if ($run->user_id && ! $run->created_by) {
                $run->created_by = $run->user_id;
            } elseif ($run->created_by && ! $run->user_id) {
                $run->user_id = $run->created_by;
            }

            if (! $run->forecast_start_date && $run->period_start) {
                $run->forecast_start_date = $run->period_start;
            }
            if (! $run->forecast_end_date && $run->period_end) {
                $run->forecast_end_date = $run->period_end;
            }
            if (! $run->period_start && $run->forecast_start_date) {
                $run->period_start = $run->forecast_start_date;
            }
            if (! $run->period_end && $run->forecast_end_date) {
                $run->period_end = $run->forecast_end_date;
            }
        });
    }

    public function forecastStart(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse(
            $this->forecast_start_date ?: $this->period_start ?: $this->start_period ?: now()
        )->startOfDay();
    }

    public function forecastEnd(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse(
            $this->forecast_end_date ?: $this->period_end ?: $this->end_period ?: $this->forecastStart()
        )->endOfDay();
    }

    public function historicalStart(): \Carbon\Carbon
    {
        if ($this->historical_start_date) {
            return \Carbon\Carbon::parse($this->historical_start_date)->startOfDay();
        }

        return $this->forecastStart()
            ->copy()
            ->subYears((int) config('salestrack.forecast.default_historical_years', 5))
            ->startOfYear();
    }

    public function historicalEnd(): \Carbon\Carbon
    {
        if ($this->historical_end_date) {
            return \Carbon\Carbon::parse($this->historical_end_date)->endOfDay();
        }

        return $this->forecastStart()->copy()->subDay()->endOfDay();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function result(): HasOne
    {
        return $this->hasOne(ForecastResult::class)->whereNull('opportunity_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ForecastResult::class);
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(ForecastScenario::class);
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(ForecastSimulation::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(ForecastRecommendation::class);
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(MasterPrincipal::class, 'principal_id');
    }
}
