<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportPeriodRequest;
use App\Services\Report\SalesReportService;
use App\Support\ApiResponse;
use App\Support\RoleChecks;
use Symfony\Component\HttpFoundation\Response;

class SalesReportController extends Controller
{
    use RoleChecks;

    public function __construct(private readonly SalesReportService $reportService) {}

    public function activity(ReportPeriodRequest $request)
    {
        if ($error = $this->restrictToOwnSales($request)) {
            return $error;
        }

        return ApiResponse::success(
            $this->reportService->activity($request->filters(), $request->user()),
            'Sales activity report retrieved successfully'
        );
    }

    public function performance(ReportPeriodRequest $request)
    {
        if ($error = $this->restrictToOwnSales($request)) {
            return $error;
        }

        return ApiResponse::success(
            $this->reportService->performance($request->filters(), $request->user()),
            'Sales performance report retrieved successfully'
        );
    }

    public function pipeline(ReportPeriodRequest $request)
    {
        if ($error = $this->restrictToOwnSales($request)) {
            return $error;
        }

        return ApiResponse::success(
            $this->reportService->pipeline($request->filters(), $request->user()),
            'Sales pipeline report retrieved successfully'
        );
    }

    public function export(ReportPeriodRequest $request)
    {
        if ($error = $this->restrictToOwnSales($request)) {
            return $error;
        }

        $content = $this->reportService->export($request->filters(), $request->user());

        return new Response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sales-performance-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    private function restrictToOwnSales(ReportPeriodRequest $request): ?Response
    {
        $user = $request->user();
        $filters = $request->filters();

        if (
            $this->isSales($user)
            && isset($filters['sales_id'])
            && (int) $filters['sales_id'] !== $user->id
        ) {
            return ApiResponse::forbidden('You can only view your own sales report');
        }

        return null;
    }
}
