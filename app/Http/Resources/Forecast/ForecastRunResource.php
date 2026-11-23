<?php

namespace App\Http\Resources\Forecast;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForecastRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'forecast_name' => $this->forecast_name,
            'forecast_type' => $this->forecast_type,
            'forecast_scope' => $this->forecast_scope,
            'revenue_category' => $this->revenue_category,
            'start_period' => $this->start_period?->toDateString() ?: $this->period_start?->toDateString(),
            'end_period' => $this->end_period?->toDateString() ?: $this->period_end?->toDateString(),
            'period_start' => $this->period_start?->toDateString() ?: $this->start_period?->toDateString(),
            'period_end' => $this->period_end?->toDateString() ?: $this->end_period?->toDateString(),
            'historical_start_date' => $this->historical_start_date?->toDateString(),
            'historical_end_date' => $this->historical_end_date?->toDateString(),
            'forecast_start_date' => $this->forecast_start_date?->toDateString()
                ?: $this->period_start?->toDateString(),
            'forecast_end_date' => $this->forecast_end_date?->toDateString()
                ?: $this->period_end?->toDateString(),
            'principal' => $this->whenLoaded('principal', fn () => $this->principal ? [
                'id' => $this->principal->id,
                'name' => $this->principal->name,
            ] : null),
            'warnings' => $this->warnings ?? [],
            'simulation_count' => $this->simulation_count,
            'confidence_level' => $this->confidence_level,
            'target_revenue' => $this->target_revenue !== null ? (float) $this->target_revenue : null,
            'predicted_revenue' => $this->predicted_revenue !== null ? (float) $this->predicted_revenue : null,
            'best_case' => $this->best_case !== null ? (float) $this->best_case : null,
            'worst_case' => $this->worst_case !== null ? (float) $this->worst_case : null,
            'most_likely' => $this->most_likely !== null ? (float) $this->most_likely : null,
            'actual_revenue' => $this->actual_revenue !== null ? (float) $this->actual_revenue : null,
            'mape' => $this->mape !== null ? (float) $this->mape : null,
            'status' => $this->status,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
            'result' => $this->whenLoaded('result', fn () => $this->result ? new ForecastResultResource($this->result) : null),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
