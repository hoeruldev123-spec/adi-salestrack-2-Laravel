<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Forecast\ForecastRecommendationResource;
use App\Models\ForecastRecommendation;
use App\Services\Forecast\RecommendationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(private readonly RecommendationService $recommendationService)
    {
        $this->authorizeResource(ForecastRecommendation::class, 'recommendation');
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'forecast_run_id', 'priority', 'recommendation_type', 'search', 'per_page',
        ]);

        $recommendations = $this->recommendationService->paginate($filters);
        $summary = $this->recommendationService->summary($filters);

        return response()->json([
            'success' => true,
            'message' => 'Recommendations retrieved successfully',
            'data' => ForecastRecommendationResource::collection($recommendations),
            'meta' => [
                'current_page' => $recommendations->currentPage(),
                'per_page' => $recommendations->perPage(),
                'total' => $recommendations->total(),
                'last_page' => $recommendations->lastPage(),
                'from' => $recommendations->firstItem(),
                'to' => $recommendations->lastItem(),
                'summary' => $summary,
            ],
        ]);
    }

    public function show(ForecastRecommendation $recommendation)
    {
        return ApiResponse::success(
            new ForecastRecommendationResource(
                $recommendation->load([
                    'forecastRun',
                    'opportunity' => fn ($query) => $query->withMax('activities', 'activity_date'),
                    'opportunity.principal',
                ])
            ),
            'Recommendation retrieved successfully'
        );
    }
}
