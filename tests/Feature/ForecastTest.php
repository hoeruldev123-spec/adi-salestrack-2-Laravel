<?php

namespace Tests\Feature;

use App\Enums\ForecastType;
use App\Enums\Role;
use App\Models\Company;
use App\Models\ForecastRun;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Role as RoleModel;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\User;
use App\Services\Forecast\MonteCarloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = RoleModel::firstOrCreate(['name' => Role::SALES_MANAGER->value], ['description' => 'Manager']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function makeRun(array $overrides = []): ForecastRun
    {
        return ForecastRun::create(array_merge([
            'name' => 'Test Forecast',
            'forecast_type' => ForecastType::OPPORTUNITY->value,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'simulation_count' => 1000,
            'confidence_level' => 90,
            'target_revenue' => 500000000,
            'status' => 'COMPLETED',
            'created_by' => $this->manager()->id,
        ], $overrides));
    }

    public function test_manager_can_create_forecast_run(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->manager(), 'sanctum')
            ->postJson('/api/v1/forecast-runs', [
                'name' => 'September 2026 Revenue Forecast',
                'forecast_type' => 'OPPORTUNITY',
                'period_start' => '2026-09-01',
                'period_end' => '2026-09-30',
                'simulation_count' => 1000,
                'confidence_level' => 90,
                'target_revenue' => 600000000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'PENDING');
    }

    public function test_sales_cannot_create_forecast_run(): void
    {
        $role = RoleModel::firstOrCreate(['name' => Role::SALES->value], ['description' => 'Sales']);
        $sales = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($sales, 'sanctum')
            ->postJson('/api/v1/forecast-runs', [
                'name' => 'X',
                'forecast_type' => 'OPPORTUNITY',
                'period_start' => '2026-09-01',
                'period_end' => '2026-09-30',
            ])
            ->assertForbidden();
    }

    public function test_forecast_run_is_processed_asynchronously_and_stores_result(): void
    {
        $company = Company::factory()->create();
        Opportunity::factory()->create([
            'value' => 500000000,
            'probability' => 80,
            'expected_close_date' => '2026-09-15',
            'status' => 'OPEN',
        ]);

        $manager = $this->manager();

        $created = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/forecast-runs', [
                'name' => 'September 2026 Revenue Forecast',
                'forecast_type' => 'OPPORTUNITY',
                'period_start' => '2026-09-01',
                'period_end' => '2026-09-30',
                'simulation_count' => 1000,
                'confidence_level' => 90,
                'target_revenue' => 400000000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'PENDING');

        $runId = $created->json('data.forecast_run_id');

        $data = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/forecast-runs/{$runId}/results")
            ->assertOk()
            ->json('data');

        $this->assertNotNull($data);
        $this->assertArrayHasKey('p10', $data);
        $this->assertArrayHasKey('p50', $data);
        $this->assertArrayHasKey('p90', $data);
        $this->assertArrayHasKey('target_probability', $data);
        $this->assertGreaterThanOrEqual(0, $data['minimum']);
    }

    public function test_forecast_evaluation_metrics(): void
    {
        $company = Company::factory()->create();
        $transaction = SalesTransaction::create([
            'company_id' => $company->id,
            'user_id' => $this->manager()->id,
            'transaction_date' => '2026-09-20',
            'transaction_number' => 'STX-TEST-001',
            'total_amount' => 500000000,
            'status' => 'PAID',
        ]);
        SalesTransactionItem::create([
            'sales_transaction_id' => $transaction->id,
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'unit_price' => 500000000,
            'subtotal' => 500000000,
        ]);

        $run = $this->makeRun();
        $run->result()->create([
            'minimum' => 0, 'maximum' => 1000000000,
            'mean' => 470000000, 'median' => 450000000,
            'p10' => 100000000, 'p25' => 250000000, 'p50' => 450000000,
            'p75' => 650000000, 'p90' => 800000000, 'p95' => 900000000,
            'target_probability' => 40,
        ]);

        $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecast-runs/{$run->id}/evaluate")
            ->assertOk()
            ->assertJsonPath('data.status', 'EVALUATED')
            ->assertJsonPath('data.actual_revenue', 500000000)
            ->assertJsonPath('data.metrics.mae', 30000000)
            ->assertJsonPath('data.metrics.mape', 6)
            ->assertJsonPath('data.metrics.rmse', 30000000);
    }

    public function test_monte_carlo_statistics_are_mathematically_consistent(): void
    {
        $monteCarlo = app(MonteCarloService::class);

        $results = $monteCarlo->simulateOpportunities([
            ['value' => 500000000, 'probability' => 1.0],
            ['value' => 300000000, 'probability' => 0.0],
            ['value' => 200000000, 'probability' => 0.5],
        ], 10000, 123);

        $stats = $monteCarlo->calculateStatistics($results, 400000000);

        $this->assertSame(500000000.0, $stats['minimum']);
        $this->assertSame(700000000.0, $stats['maximum']);
        $this->assertGreaterThan(590000000, $stats['mean']);
        $this->assertLessThan(610000000, $stats['mean']);
        $this->assertGreaterThanOrEqual(0, $stats['target_probability']);
        $this->assertLessThanOrEqual(100, $stats['target_probability']);
    }
}
