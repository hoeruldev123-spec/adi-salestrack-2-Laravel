<?php

namespace App\Http\Resources\Forecast;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForecastRecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'forecast_run_id' => $this->forecast_run_id,
            'opportunity_id' => $this->opportunity_id,
            'recommendation_type' => $this->recommendation_type,
            'priority' => $this->priority,
            'recommendation_title' => $this->recommendation_title,
            'recommendation_description' => $this->recommendation_description,
            'estimated_value' => $this->estimated_value !== null ? (float) $this->estimated_value : null,
            'probability' => $this->probability !== null ? (float) $this->probability : null,
            'action_suggestion' => $this->action_suggestion,
            'forecast_run' => $this->whenLoaded('forecastRun', fn () => $this->forecastRun ? [
                'id' => $this->forecastRun->id,
                'forecast_name' => $this->forecastRun->forecast_name ?: $this->forecastRun->name,
                'forecast_start_date' => $this->forecastRun->forecast_start_date?->toDateString(),
                'forecast_end_date' => $this->forecastRun->forecast_end_date?->toDateString(),
            ] : null),
            'opportunity' => $this->whenLoaded('opportunity', fn () => $this->opportunity ? [
                'id' => $this->opportunity->id,
                'title' => $this->opportunity->title ?: $this->opportunity->project_name,
                'client_name' => $this->opportunity->client_name,
                'stage' => $this->opportunity->stage,
                'status' => $this->opportunity->status,
                'closing_date' => $this->opportunity->closing_date?->toDateString()
                    ?: $this->opportunity->expected_close_date?->toDateString(),
                'estimated_value' => $this->opportunity->estimated_value !== null
                    ? (float) $this->opportunity->estimated_value
                    : (float) $this->opportunity->value,
                'principal' => $this->opportunity->relationLoaded('principal') && $this->opportunity->principal
                    ? [
                        'id' => $this->opportunity->principal->id,
                        'name' => $this->opportunity->principal->name,
                    ]
                    : null,
                'last_activity_at' => $this->opportunity->activities_max_activity_date,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
