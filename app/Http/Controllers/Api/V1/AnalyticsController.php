<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->analyticsService->dashboard($request->query('year')),
            'Dashboard analytics retrieved successfully'
        );
    }

    public function revenue(Request $request)
    {
        return ApiResponse::success(
            $this->analyticsService->revenue($request->only(['year', 'from', 'to'])),
            'Revenue analytics retrieved successfully'
        );
    }

    public function pipeline()
    {
        return ApiResponse::success(
            $this->analyticsService->pipeline(),
            'Pipeline analytics retrieved successfully'
        );
    }

    public function performance(Request $request)
    {
        return ApiResponse::success(
            $this->analyticsService->performance($request->only(['year', 'from', 'to'])),
            'Performance analytics retrieved successfully'
        );
    }
}
