<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportPeriodRequest;
use App\Services\Report\RevenueReportService;
use App\Support\ApiResponse;

class RevenueReportController extends Controller
{
    public function __construct(private readonly RevenueReportService $reportService) {}

    public function overview(ReportPeriodRequest $request)
    {
        return ApiResponse::success(
            $this->reportService->overview($request->filters()),
            'Revenue overview report retrieved successfully'
        );
    }

    public function forecast(ReportPeriodRequest $request)
    {
        return ApiResponse::success(
            $this->reportService->forecast(),
            'Revenue forecast report retrieved successfully'
        );
    }

    public function comparison(ReportPeriodRequest $request)
    {
        return ApiResponse::success(
            $this->reportService->comparison($request->filters()),
            'Revenue comparison report retrieved successfully'
        );
    }
}
