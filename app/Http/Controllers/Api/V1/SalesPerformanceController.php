<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Sales\SalesPerformanceService;
use App\Support\ApiResponse;

class SalesPerformanceController extends Controller
{
    public function __construct(private readonly SalesPerformanceService $performanceService) {}

    public function leaderboard()
    {
        return ApiResponse::success(
            $this->performanceService->leaderboard((int) config('salestrack.leaderboard_size', 5)),
            'Sales leaderboard retrieved successfully'
        );
    }

    public function teamSummary()
    {
        return ApiResponse::success(
            $this->performanceService->teamSummary(),
            'Team summary retrieved successfully'
        );
    }
}
