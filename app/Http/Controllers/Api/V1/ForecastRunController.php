<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreForecastRunRequest;
use App\Http\Requests\StoreForecastScenarioRequest;
use App\Http\Resources\Forecast\ForecastResultResource;
use App\Http\Resources\Forecast\ForecastRunResource;
use App\Http\Resources\Forecast\ForecastScenarioResource;
use App\Jobs\RunMonteCarloSimulation;
use App\Models\ForecastRun;
use App\Models\ForecastScenario;
use App\Services\Forecast\ForecastRunService;
use App\Services\Forecast\ScenarioForecastService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ForecastRunController extends Controller
{
    public function __construct(private readonly ForecastRunService $runService)
    {
        $this->authorizeResource(ForecastRun::class, 'forecast_run');
    }

    public function index(Request $request)
    {
        $runs = $this->runService->paginate($request->only(['forecast_type', 'status', 'per_page']));

        return ApiResponse::paginated(
            ForecastRunResource::collection($runs),
            'Forecast runs retrieved successfully'
        );
    }

    public function show(ForecastRun $forecastRun)
    {
        return ApiResponse::success(
            new ForecastRunResource(
                $forecastRun->load(['createdBy', 'result', 'scenarios'])
            ),
            'Forecast run retrieved successfully'
        );
    }

    public function store(StoreForecastRunRequest $request)
    {
        $run = $this->runService->create($request->validated(), $request->user());

        RunMonteCarloSimulation::dispatch($run);

        return ApiResponse::created([
            'forecast_run_id' => $run->id,
            'status' => $run->status,
        ], 'Forecast run created and queued');
    }

    public function destroy(ForecastRun $forecastRun)
    {
        $this->runService->destroy($forecastRun);

        return ApiResponse::success(null, 'Forecast run deleted successfully');
    }

    public function results(ForecastRun $forecastRun)
    {
        $this->authorize('view', $forecastRun);

        if (! $forecastRun->result) {
            return ApiResponse::success(null, 'Forecast result is not available yet');
        }

        return ApiResponse::success(
            new ForecastResultResource($forecastRun->result->load('forecastRun')),
            'Forecast result retrieved successfully'
        );
    }

    public function scenarios(Request $request, ForecastRun $forecastRun)
    {
        $this->authorize('view', $forecastRun);

        $scenarios = $this->runService->scenarios($forecastRun, $request->only(['per_page']));

        return ApiResponse::paginated(
            ForecastScenarioResource::collection($scenarios),
            'Forecast scenarios retrieved successfully'
        );
    }

    public function storeScenario(StoreForecastScenarioRequest $request, ForecastRun $forecastRun)
    {
        $this->authorize('view', $forecastRun);

        $scenario = $this->runService->createScenario($forecastRun, $request->validated());

        return ApiResponse::created(
            new ForecastScenarioResource($scenario),
            'Forecast scenario created successfully'
        );
    }

    public function destroyScenario(ForecastRun $forecastRun, ForecastScenario $scenario)
    {
        $this->authorize('view', $forecastRun);

        $this->runService->destroyScenario($forecastRun, $scenario);

        return ApiResponse::success(null, 'Forecast scenario deleted successfully');
    }

    public function scenarioResults(ForecastRun $forecastRun, ForecastScenario $scenario)
    {
        $this->authorize('view', $forecastRun);

        if ($scenario->forecast_run_id !== $forecastRun->id) {
            return ApiResponse::notFound('Forecast scenario not found');
        }

        $statistics = app(ScenarioForecastService::class)->statistics($forecastRun, $scenario);

        return ApiResponse::success([
            'forecast_run_id' => $forecastRun->id,
            'scenario' => new ForecastScenarioResource($scenario),
            'statistics' => $statistics,
        ], 'Scenario simulation result retrieved successfully');
    }
}
