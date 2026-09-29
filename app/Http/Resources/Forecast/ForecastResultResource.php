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
            'minimum' => (float) $this->minimum,
            'maximum' => (float) $this->maximum,
            'mean' => (float) $this->mean,
            'median' => (float) $this->median,
            'p10' => (float) $this->p10,
            'p25' => (float) $this->p25,
            'p50' => (float) $this->p50,
            'p75' => (float) $this->p75,
            'p90' => (float) $this->p90,
            'p95' => (float) $this->p95,
            'target_probability' => $this->target_probability !== null ? (float) $this->target_probability : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
