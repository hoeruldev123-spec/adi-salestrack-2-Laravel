<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use App\Support\ApiResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function sales()
    {
        return ApiResponse::success(
            $this->dashboardService->sales(request()->user()),
            'Sales dashboard retrieved successfully'
        );
    }

    public function manager()
    {
        return ApiResponse::success(
            $this->dashboardService->manager(),
            'Manager dashboard retrieved successfully'
        );
    }

    public function bod()
    {
        return ApiResponse::success(
            $this->dashboardService->bod(),
            'BOD dashboard retrieved successfully'
        );
    }
}
