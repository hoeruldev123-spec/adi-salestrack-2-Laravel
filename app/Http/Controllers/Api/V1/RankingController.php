<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportPeriodRequest;
use App\Services\Report\RankingService;
use App\Support\ApiResponse;

class RankingController extends Controller
{
    public function __construct(private readonly RankingService $rankingService) {}

    public function sales(ReportPeriodRequest $request)
    {
        return ApiResponse::success(
            $this->rankingService->sales($request->filters()),
            'Sales ranking report retrieved successfully'
        );
    }

    public function principals(ReportPeriodRequest $request)
    {
        return ApiResponse::success(
            $this->rankingService->principals($request->filters()),
            'Principal ranking report retrieved successfully'
        );
    }

    public function accounts(ReportPeriodRequest $request)
    {
        return ApiResponse::success(
            $this->rankingService->accounts($request->filters()),
            'Account ranking report retrieved successfully'
        );
    }
}
