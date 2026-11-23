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
            $this->analyticsService->dashboard($request->query('year'), $request->user()),
            'Dashboard analytics retrieved successfully'
        );
    }

    public function revenue(Request $request)
    {
        return ApiResponse::success(
            $this->analyticsService->revenue($request->only(['year', 'from', 'to']), $request->user()),
            'Revenue analytics retrieved successfully'
        );
    }

    public function pipeline(Request $request)
    {
        return ApiResponse::success(
            $this->analyticsService->pipeline($request->user(), $request->query('year')),
            'Pipeline analytics retrieved successfully'
        );
    }

    public function performance(Request $request)
    {
        return ApiResponse::success(
            $this->analyticsService->performance($request->only(['year', 'from', 'to']), $request->user()),
            'Performance analytics retrieved successfully'
        );
    }
}
