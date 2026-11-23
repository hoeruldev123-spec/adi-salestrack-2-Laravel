<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'user_id', 'principal_id', 'title', 'project_name', 'client_name',
    'description', 'value', 'estimated_value', 'stage', 'progress', 'status',
    'probability', 'sa_pic', 'license_type', 'deal_registration',
    'closing_date', 'expected_close_date', 'actual_closing_date', 'source', 'remarks',
])]
class Opportunity extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'probability' => 'integer',
            'closing_date' => 'date',
            'expected_close_date' => 'date',
            'actual_closing_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Opportunity $opportunity) {
            // Synchronize project_name and title
            if ($opportunity->project_name && ! $opportunity->title) {
                $opportunity->title = $opportunity->project_name;
            } elseif ($opportunity->title && ! $opportunity->project_name) {
                $opportunity->project_name = $opportunity->title;
            } elseif ($opportunity->isDirty('project_name')) {
                $opportunity->title = $opportunity->project_name;
            } elseif ($opportunity->isDirty('title')) {
                $opportunity->project_name = $opportunity->title;
            }

            // Synchronize estimated_value and value
            if ($opportunity->estimated_value !== null && $opportunity->value === null) {
                $opportunity->value = $opportunity->estimated_value;
            } elseif ($opportunity->value !== null && $opportunity->estimated_value === null) {
                $opportunity->estimated_value = $opportunity->value;
            } elseif ($opportunity->isDirty('estimated_value')) {
                $opportunity->value = $opportunity->estimated_value;
            } elseif ($opportunity->isDirty('value')) {
                $opportunity->estimated_value = $opportunity->value;
            }

            // Synchronize progress and stage
            if ($opportunity->progress && ! $opportunity->stage) {
                $opportunity->stage = $opportunity->progress;
            } elseif ($opportunity->stage && ! $opportunity->progress) {
                $opportunity->progress = $opportunity->stage;
            } elseif ($opportunity->isDirty('progress')) {
                $opportunity->stage = $opportunity->progress;
            } elseif ($opportunity->isDirty('stage')) {
                $opportunity->progress = $opportunity->stage;
            }

            // Synchronize closing_date and expected_close_date
            if ($opportunity->closing_date && ! $opportunity->expected_close_date) {
                $opportunity->expected_close_date = $opportunity->closing_date;
            } elseif ($opportunity->expected_close_date && ! $opportunity->closing_date) {
                $opportunity->closing_date = $opportunity->expected_close_date;
            } elseif ($opportunity->isDirty('closing_date')) {
                $opportunity->expected_close_date = $opportunity->closing_date;
            } elseif ($opportunity->isDirty('expected_close_date')) {
                $opportunity->closing_date = $opportunity->expected_close_date;
            }

            // Synchronize client_name and company_id
            if (! $opportunity->company_id && $opportunity->client_name) {
                $company = Company::firstOrCreate(
                    ['name' => $opportunity->client_name],
                    ['industry' => 'Technology']
                );
                $opportunity->company_id = $company->id;
            } elseif ($opportunity->company_id && ! $opportunity->client_name) {
                $opportunity->client_name = $opportunity->company?->name;
            }
        });

        static::updated(function (Opportunity $opportunity) {
            if (! $opportunity->wasChanged(['stage', 'progress', 'probability'])) {
                return;
            }

            $previousStage = $opportunity->getOriginal('stage')
                ?: $opportunity->getOriginal('progress');
            $currentStage = $opportunity->stage ?: $opportunity->progress;

            OpportunityStageHistory::create([
                'opportunity_id' => $opportunity->id,
                'previous_stage' => $previousStage,
                'current_stage' => $currentStage ?: 'LEAD',
                'previous_probability' => $opportunity->getOriginal('probability'),
                'current_probability' => $opportunity->probability,
                'changed_at' => now(),
            ]);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(MasterPrincipal::class, 'principal_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OpportunityItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OpportunityLog::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function forecastResults(): HasMany
    {
        return $this->hasMany(ForecastResult::class);
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(OpportunityStageHistory::class);
    }

    public function forecastRecommendations(): HasMany
    {
        return $this->hasMany(ForecastRecommendation::class);
    }
}
