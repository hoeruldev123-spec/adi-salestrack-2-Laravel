<?php

namespace App\Http\Resources\Forecast;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForecastScenarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'forecast_run_id' => $this->forecast_run_id,
            'name' => $this->name,
            'description' => $this->description,
            'win_rate_adjustment' => (float) $this->win_rate_adjustment,
            'revenue_adjustment' => (float) $this->revenue_adjustment,
            'opportunity_adjustment' => (float) $this->opportunity_adjustment,
            'target_revenue' => $this->target_revenue !== null ? (float) $this->target_revenue : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
