<?php

namespace App\Services\Forecast;

use App\Enums\OpportunityStatus;
use App\Enums\ProductType;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use Carbon\Carbon;

class ForecastConfigurationService
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $availableStart = Opportunity::query()
            ->min('created_at');
        $forecastStart = now()->startOfMonth();
        $forecastEnd = now()->addMonths(2)->endOfMonth();
        $openOpportunities = Opportunity::query()
            ->where('status', OpportunityStatus::OPEN->value);

        $categories = array_values(array_filter(
            ProductType::values(),
            fn ($type) => $type !== ProductType::PRODUCT->value
        ));

        return [
            'forecast_scopes' => ['OPPORTUNITY'],
            'revenue_categories' => array_merge(['ALL'], $categories),
            'simulation' => [
                'default' => 10000,
                'min' => 100,
                'max' => 1000000,
            ],
            'principals' => MasterPrincipal::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (MasterPrincipal $principal) => [
                    'id' => $principal->id,
                    'name' => $principal->name,
                ])
                ->all(),
            'historical_period' => [
                'available_start' => $availableStart
                    ? Carbon::parse($availableStart)->toDateString()
                    : null,
                'available_end' => $forecastStart->copy()->subDay()->toDateString(),
            ],
            'forecast_period' => [
                'suggested_start' => $forecastStart->toDateString(),
                'suggested_end' => $forecastEnd->toDateString(),
            ],
            'recommendation_thresholds' => config('salestrack.forecast.recommendations', []),
            'opportunity_data' => [
                'open_count' => (clone $openOpportunities)->count(),
                'has_data' => (clone $openOpportunities)->exists(),
            ],
        ];
    }
}
