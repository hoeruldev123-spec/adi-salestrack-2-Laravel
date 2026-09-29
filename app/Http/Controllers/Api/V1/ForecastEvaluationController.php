<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ForecastRun;
use App\Models\ForecastScenario;
use App\Services\Forecast\ForecastEvaluationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ForecastEvaluationController extends Controller
{
    public function __construct(private readonly ForecastEvaluationService $evaluationService) {}

    public function evaluate(ForecastRun $forecastRun)
    {
        $this->authorize('view', $forecastRun);

        try {
            $result = $this->evaluationService->evaluateRun($forecastRun);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }

        return ApiResponse::success($result, 'Forecast evaluation retrieved successfully');
    }

    public function backtest(Request $request, ForecastRun $forecastRun)
    {
        $this->authorize('view', $forecastRun);

        try {
            $result = $this->evaluationService->historicalBacktest(
                $forecastRun,
                (int) ($request->query('periods', 6)),
                $request->has('seed') ? (int) $request->query('seed') : null
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }

        return ApiResponse::success($result, 'Historical backtest completed successfully');
    }

    public function compareScenario(ForecastRun $forecastRun, ForecastScenario $scenario)
    {
        $this->authorize('view', $forecastRun);

        if ($scenario->forecast_run_id !== $forecastRun->id) {
            return ApiResponse::notFound('Forecast scenario not found');
        }

        return ApiResponse::success(
            $this->evaluationService->compareScenarioToBaseline($forecastRun, $scenario),
            'Scenario comparison completed successfully'
        );
    }
}
