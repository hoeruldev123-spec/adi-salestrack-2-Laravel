<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ForecastEvaluationController;
use App\Http\Controllers\Api\V1\ForecastRunController;
use App\Http\Controllers\Api\V1\MasterPrincipalController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\PriceListController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductSpecificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RankingController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\RevenueReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SalesPerformanceController;
use App\Http\Controllers\Api\V1\SalesProfileController;
use App\Http\Controllers\Api\V1\SalesReportController;
use App\Http\Controllers\Api\V1\SalesTransactionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);

        Route::get('roles', [RoleController::class, 'index']);
        Route::get('users/sales', [UserController::class, 'sales'])
            ->middleware('role:ADMIN,SALES_MANAGER,MANAGEMENT');
        Route::apiResource('users', UserController::class);
        Route::apiResource('companies', CompanyController::class);
        Route::apiResource('principals', MasterPrincipalController::class);
        Route::apiResource('product-categories', ProductCategoryController::class)->parameters([
            'product-categories' => 'product_category',
        ]);
        Route::apiResource('products', ProductController::class);
        Route::get('products/{product}/specifications', [ProductSpecificationController::class, 'index']);
        Route::post('products/{product}/specifications', [ProductSpecificationController::class, 'store']);
        Route::put('product-specifications/{specification}', [ProductSpecificationController::class, 'update']);
        Route::delete('product-specifications/{specification}', [ProductSpecificationController::class, 'destroy']);
        Route::apiResource('price-lists', PriceListController::class)->parameters([
            'price-lists' => 'price_list',
        ]);

        Route::prefix('dashboard')->group(function () {
            Route::get('sales', [DashboardController::class, 'sales'])->middleware('role:SALES,ADMIN');
            Route::get('manager', [DashboardController::class, 'manager'])->middleware('role:SALES_MANAGER,ADMIN');
            Route::get('bod', [DashboardController::class, 'bod'])->middleware('role:MANAGEMENT,ADMIN');
        });

        Route::prefix('reports')->group(function () {
            Route::prefix('sales')->middleware('role:SALES,SALES_MANAGER,MANAGEMENT,ADMIN')->group(function () {
                Route::get('activity', [SalesReportController::class, 'activity']);
                Route::get('performance', [SalesReportController::class, 'performance']);
                Route::get('pipeline', [SalesReportController::class, 'pipeline']);
                Route::get('export', [SalesReportController::class, 'export']);
            });

            Route::prefix('revenue')->middleware('role:SALES_MANAGER,MANAGEMENT,ADMIN')->group(function () {
                Route::get('overview', [RevenueReportController::class, 'overview']);
                Route::get('forecast', [RevenueReportController::class, 'forecast']);
                Route::get('comparison', [RevenueReportController::class, 'comparison']);
            });

            Route::prefix('ranking')->middleware('role:SALES_MANAGER,MANAGEMENT,ADMIN')->group(function () {
                Route::get('sales', [RankingController::class, 'sales']);
                Route::get('principals', [RankingController::class, 'principals']);
                Route::get('accounts', [RankingController::class, 'accounts']);
            });
        });

        Route::get('sales-profiles', [SalesProfileController::class, 'index'])
            ->middleware('role:SALES_MANAGER,MANAGEMENT,ADMIN');
        Route::get('sales-profiles/{id}', [SalesProfileController::class, 'show'])->whereNumber('id');
        Route::put('sales-profiles/{id}', [SalesProfileController::class, 'update'])->whereNumber('id');

        Route::get('sales-performance/leaderboard', [SalesPerformanceController::class, 'leaderboard'])
            ->middleware('role:SALES_MANAGER,MANAGEMENT,ADMIN');
        Route::get('sales-performance/team-summary', [SalesPerformanceController::class, 'teamSummary'])
            ->middleware('role:SALES_MANAGER,MANAGEMENT,ADMIN');

        Route::get('activity-logs', [ActivityLogController::class, 'index'])
            ->middleware('role:SALES_MANAGER,MANAGEMENT,ADMIN');

        Route::apiResource('opportunities', OpportunityController::class);
        Route::get('opportunities/{opportunity}/items', [OpportunityController::class, 'items']);
        Route::post('opportunities/{opportunity}/items', [OpportunityController::class, 'addItem']);
        Route::get('opportunities/{opportunity}/logs', [OpportunityController::class, 'logs']);
        Route::apiResource('opportunities.activities', ActivityController::class)->scoped();
        Route::apiResource('activities', ActivityController::class)->except(['store', 'index']);

        Route::apiResource('transactions', SalesTransactionController::class)->parameters([
            'transaction' => 'transaction',
        ]);
        Route::get('transactions/{transaction}/items', [SalesTransactionController::class, 'items']);

        Route::prefix('analytics')->middleware('role:ADMIN,SALES_MANAGER,MANAGEMENT,SALES')->group(function () {
            Route::get('dashboard', [AnalyticsController::class, 'dashboard']);
            Route::get('revenue', [AnalyticsController::class, 'revenue']);
            Route::get('pipeline', [AnalyticsController::class, 'pipeline']);
            Route::get('performance', [AnalyticsController::class, 'performance']);
        });

        Route::apiResource('forecast-runs', ForecastRunController::class)->only([
            'index', 'show', 'store', 'destroy',
        ]);

        Route::middleware('role:ADMIN,SALES_MANAGER,MANAGEMENT')->group(function () {
            Route::get('forecasting/configuration', [ForecastRunController::class, 'configuration'])
                ->middleware('role:ADMIN,SALES_MANAGER');
            Route::get('forecasting', [ForecastRunController::class, 'index']);
            Route::get('forecasting/{forecast_run}', [ForecastRunController::class, 'show']);
            Route::get('forecasting/{forecast_run}/results', [ForecastRunController::class, 'results']);
            Route::get('forecasting/{forecast_run}/distribution', [ForecastRunController::class, 'distribution']);
            Route::get('forecasting/{forecast_run}/principals', [ForecastRunController::class, 'principals']);
            Route::get('forecasting/{forecast_run}/categories', [ForecastRunController::class, 'categories']);
            Route::get('forecasting/{forecast_run}/evaluation', [ForecastRunController::class, 'evaluation']);
            Route::get('forecasting/{forecast_run}/recommendations', [ForecastRunController::class, 'recommendations']);

            Route::get('recommendations', [RecommendationController::class, 'index']);
            Route::get('recommendations/{recommendation}', [RecommendationController::class, 'show']);

            Route::post('forecasting/run', [ForecastRunController::class, 'store'])
                ->middleware('role:ADMIN,SALES_MANAGER');
        });

        Route::get('forecast-runs/{forecast_run}/results', [ForecastRunController::class, 'results']);
        Route::get('forecast-runs/{forecast_run}/scenarios', [ForecastRunController::class, 'scenarios']);
        Route::post('forecast-runs/{forecast_run}/scenarios', [ForecastRunController::class, 'storeScenario']);
        Route::delete('forecast-runs/{forecast_run}/scenarios/{scenario}', [ForecastRunController::class, 'destroyScenario']);
        Route::get('forecast-runs/{forecast_run}/scenarios/{scenario}/results', [ForecastRunController::class, 'scenarioResults']);
        Route::get('forecast-runs/{forecast_run}/evaluate', [ForecastEvaluationController::class, 'evaluate']);
        Route::get('forecast-runs/{forecast_run}/backtest', [ForecastEvaluationController::class, 'backtest']);
        Route::get('forecast-runs/{forecast_run}/scenarios/{scenario}/compare', [ForecastEvaluationController::class, 'compareScenario']);
    });
});
