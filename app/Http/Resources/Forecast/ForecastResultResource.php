<?php

namespace App\Http\Resources\Forecast;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForecastResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'forecast_run_id' => $this->forecast_run_id,
            'opportunity_id' => $this->opportunity_id,
            'opportunity' => $this->whenLoaded('opportunity', fn () => $this->opportunity ? [
                'id' => $this->opportunity->id,
                'title' => $this->opportunity->title,
            ] : null),
            'simulated_value' => $this->simulated_value !== null ? (float) $this->simulated_value : null,
            'probability' => $this->probability !== null ? (float) $this->probability : null,
            'simulation_iteration' => $this->simulation_iteration,
            'minimum' => $this->minimum !== null ? (float) $this->minimum : null,
            'maximum' => $this->maximum !== null ? (float) $this->maximum : null,
            'mean' => $this->mean !== null ? (float) $this->mean : null,
            'median' => $this->median !== null ? (float) $this->median : null,
            'p10' => $this->p10 !== null ? (float) $this->p10 : null,
            'p25' => $this->p25 !== null ? (float) $this->p25 : null,
            'p50' => $this->p50 !== null ? (float) $this->p50 : null,
            'p75' => $this->p75 !== null ? (float) $this->p75 : null,
            'p90' => $this->p90 !== null ? (float) $this->p90 : null,
            'p95' => $this->p95 !== null ? (float) $this->p95 : null,
            'target_probability' => $this->target_probability !== null ? (float) $this->target_probability : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
