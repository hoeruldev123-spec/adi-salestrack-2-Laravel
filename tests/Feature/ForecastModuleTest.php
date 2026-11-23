<?php

namespace Tests\Feature;

use App\Enums\OpportunityStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\Product;
use App\Models\Role as RoleModel;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastModuleTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = RoleModel::firstOrCreate(
            ['name' => Role::SALES_MANAGER->value],
            ['description' => 'Manager']
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function sales(): User
    {
        $role = RoleModel::firstOrCreate(
            ['name' => Role::SALES->value],
            ['description' => 'Sales']
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * @return array{0: MasterPrincipal, 1: Opportunity, 2: array<string, string>}
     */
    private function seedForecastScenario(
        string $stage = 'NEGOTIATION',
        float $value = 1_500_000_000,
        int $wonCount = 4,
        int $lostCount = 1
    ): array
    {
        $manager = $this->manager();
        $principal = MasterPrincipal::factory()->create(['name' => 'AWS']);
        $company = Company::factory()->create();

        $forecastStart = now()->startOfMonth();
        $forecastEnd = now()->copy()->addMonths(2)->endOfMonth();
        $historicalStart = now()->copy()->subYears(2)->startOfMonth();
        $historicalEnd = $forecastStart->copy()->subDay();

        for ($i = 0; $i < $wonCount; $i++) {
            Opportunity::factory()->create([
                'company_id' => $company->id,
                'user_id' => $manager->id,
                'principal_id' => $principal->id,
                'stage' => $stage,
                'status' => OpportunityStatus::WON->value,
                'probability' => 100,
                'value' => 100_000_000,
                'closing_date' => $historicalStart->copy()->addDays($i)->toDateString(),
                'expected_close_date' => $historicalStart->copy()->addDays($i)->toDateString(),
            ]);
        }

        for ($i = 0; $i < $lostCount; $i++) {
            Opportunity::factory()->create([
                'company_id' => $company->id,
                'user_id' => $manager->id,
                'principal_id' => $principal->id,
                'stage' => $stage,
                'status' => OpportunityStatus::LOST->value,
                'probability' => 0,
                'value' => 100_000_000,
                'closing_date' => $historicalStart->copy()->addDays($i + 10)->toDateString(),
                'expected_close_date' => $historicalStart->copy()->addDays($i + 10)->toDateString(),
            ]);
        }

        $opportunity = Opportunity::factory()->create([
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'principal_id' => $principal->id,
            'title' => 'Enterprise Data Platform',
            'stage' => $stage,
            'status' => OpportunityStatus::OPEN->value,
            'probability' => 50,
            'value' => 0,
            'estimated_value' => 0,
            'closing_date' => now()->addDays(10)->toDateString(),
            'expected_close_date' => now()->addDays(10)->toDateString(),
        ]);

        $licenseProduct = Product::factory()->create([
            'principal_id' => $principal->id,
            'name' => 'Enterprise License',
            'type' => 'LICENSE',
            'product_type' => 'LICENSE',
            'price' => $value,
        ]);

        $serviceProduct = Product::factory()->create([
            'principal_id' => $principal->id,
            'name' => 'Implementation Service',
            'type' => 'SERVICE',
            'product_type' => 'SERVICE',
            'price' => 250_000_000,
        ]);

        $opportunity->items()->create([
            'product_id' => $licenseProduct->id,
            'item_description' => $licenseProduct->name,
            'quantity' => 1,
            'unit_price' => $value,
            'discount' => 0,
            'subtotal' => $value,
        ]);

        $opportunity->items()->create([
            'product_id' => $serviceProduct->id,
            'item_description' => $serviceProduct->name,
            'quantity' => 1,
            'unit_price' => 250_000_000,
            'discount' => 0,
            'subtotal' => 250_000_000,
        ]);

        $opportunity->update([
            'value' => $value + 250_000_000,
            'estimated_value' => $value + 250_000_000,
        ]);

        return [
            $principal,
            $opportunity,
            [
                'historical_start_date' => $historicalStart->toDateString(),
                'historical_end_date' => $historicalEnd->toDateString(),
                'forecast_start_date' => $forecastStart->toDateString(),
                'forecast_end_date' => $forecastEnd->toDateString(),
            ],
        ];
    }

    public function test_manager_can_view_forecasting_configuration(): void
    {
        $principal = MasterPrincipal::factory()->create(['name' => 'AWS']);

        $this->actingAs($this->manager(), 'sanctum')
            ->getJson('/api/v1/forecasting/configuration')
            ->assertOk()
            ->assertJsonPath('data.revenue_categories.0', 'ALL')
            ->assertJsonPath('data.principals.0.name', 'AWS')
            ->assertJsonPath('data.simulation.default', 10000);
    }

    public function test_sales_cannot_access_forecasting_configuration(): void
    {
        $this->actingAs($this->sales(), 'sanctum')
            ->getJson('/api/v1/forecasting/configuration')
            ->assertForbidden();
    }

    public function test_historical_period_overlapping_forecast_period_is_rejected(): void
    {
        $this->actingAs($this->manager(), 'sanctum')
            ->postJson('/api/v1/forecasting/run', [
                'forecast_name' => 'Leakage Forecast',
                'historical_start_date' => '2025-01-01',
                'historical_end_date' => '2026-02-01',
                'forecast_start_date' => '2026-01-01',
                'forecast_end_date' => '2026-03-31',
                'simulation_count' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('historical_end_date');
    }

    public function test_monte_carlo_run_produces_prediction_result_and_breakdowns(): void
    {
        [$principal, $opportunity, $periods] = $this->seedForecastScenario();

        $response = $this->actingAs($this->manager(), 'sanctum')
            ->postJson('/api/v1/forecasting/run', array_merge($periods, [
                'forecast_name' => 'Revenue Forecast Q1',
                'simulation_count' => 500,
            ]))
            ->assertCreated();

        $runId = $response->json('data.forecast_run_id');

        $run = $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecasting/{$runId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->json('data');

        $this->assertNotNull($run['predicted_revenue']);
        $this->assertNotNull($run['best_case']);
        $this->assertNotNull($run['worst_case']);
        $this->assertNotNull($run['most_likely']);

        $result = $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecasting/{$runId}/results")
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $result['total_opportunities']);
        $this->assertSame(500, $result['simulation_count']);
        $this->assertEquals(1_750_000_000, $result['total_opportunity_value']);
        $this->assertGreaterThan(0, $result['expected_revenue']);
        $this->assertGreaterThanOrEqual($result['worst_case'], $result['most_likely']);
        $this->assertGreaterThanOrEqual($result['most_likely'], $result['best_case']);
        $this->assertGreaterThan(1_200_000_000, $result['expected_revenue']);
        $this->assertLessThan(1_600_000_000, $result['expected_revenue']);
        $this->assertEquals(80, $result['opportunities'][0]['probability_percent']);

        $distribution = $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecasting/{$runId}/distribution")
            ->assertOk()
            ->json('data');

        $this->assertSame(500, $distribution['iteration_count']);
        $this->assertNotEmpty($distribution['histogram']);
        $this->assertTrue($distribution['p10'] <= $distribution['p50']);
        $this->assertTrue($distribution['p50'] <= $distribution['p90']);

        $principals = $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecasting/{$runId}/principals")
            ->assertOk()
            ->json('data');

        $this->assertSame('AWS', $principals[0]['principal']);
        $this->assertEquals(1_750_000_000, $principals[0]['estimated_value']);
        $this->assertGreaterThan(1_200_000_000, $principals[0]['expected_revenue']);

        $categories = $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecasting/{$runId}/categories")
            ->assertOk()
            ->json('data');

        $license = collect($categories)->firstWhere('category', 'LICENSE');
        $service = collect($categories)->firstWhere('category', 'SERVICE');

        $this->assertEquals(1_500_000_000, $license['estimated_value']);
        $this->assertEquals(250_000_000, $service['estimated_value']);

        $recommendations = $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecasting/{$runId}/recommendations")
            ->assertOk()
            ->json('data');

        $types = array_column($recommendations, 'recommendation_type');
        $this->assertContains('HIGH_POTENTIAL', $types);
        $this->assertContains('CLOSING_SOON', $types);
        $this->assertContains('INACTIVE_OPPORTUNITY', $types);
    }

    public function test_forecast_evaluation_reports_periods_and_handles_zero_actual(): void
    {
        [, , $periods] = $this->seedForecastScenario();

        $runId = $this->actingAs($this->manager(), 'sanctum')
            ->postJson('/api/v1/forecasting/run', array_merge($periods, [
                'forecast_name' => 'Evaluation Forecast',
                'simulation_count' => 200,
            ]))
            ->assertCreated()
            ->json('data.forecast_run_id');

        $evaluation = $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecasting/{$runId}/evaluation")
            ->assertOk()
            ->json('data');

        $this->assertSame('NO_ACTUAL_DATA', $evaluation['status']);
        $this->assertNull($evaluation['mape']);
        $this->assertCount(3, $evaluation['periods']);
        $this->assertSame('NO_ACTUAL', $evaluation['periods'][0]['status']);
    }

    public function test_monthly_evaluation_calculates_mape_when_actual_transaction_exists(): void
    {
        [, $opportunity, $periods] = $this->seedForecastScenario();

        SalesTransaction::factory()->create([
            'opportunity_id' => $opportunity->id,
            'company_id' => $opportunity->company_id,
            'user_id' => $opportunity->user_id,
            'transaction_date' => now()->toDateString(),
            'total_amount' => 1_200_000_000,
            'status' => 'PAID',
        ]);

        $runId = $this->actingAs($this->manager(), 'sanctum')
            ->postJson('/api/v1/forecasting/run', array_merge($periods, [
                'forecast_name' => 'Evaluation With Actual',
                'simulation_count' => 200,
            ]))
            ->assertCreated()
            ->json('data.forecast_run_id');

        $evaluation = $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecasting/{$runId}/evaluation")
            ->assertOk()
            ->json('data');

        $this->assertSame('EVALUATED', $evaluation['status']);
        $this->assertNotNull($evaluation['mape']);
        $this->assertGreaterThan(0, $evaluation['mape']);
        $this->assertSame(1, $evaluation['evaluated_periods']);
        $this->assertSame(1_200_000_000, $evaluation['total_actual']);
        $this->assertNotEmpty($evaluation['interpretation']);
    }

    public function test_recommendations_include_high_value_low_probability_rule(): void
    {
        [, , $periods] = $this->seedForecastScenario('PROPOSAL', 1_500_000_000, 1, 9);

        $runId = $this->actingAs($this->manager(), 'sanctum')
            ->postJson('/api/v1/forecasting/run', array_merge($periods, [
                'forecast_name' => 'Low Probability Forecast',
                'simulation_count' => 100,
            ]))
            ->assertCreated()
            ->json('data.forecast_run_id');

        $recommendations = $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/forecasting/{$runId}/recommendations")
            ->assertOk()
            ->json('data');

        $types = array_column($recommendations, 'recommendation_type');

        $this->assertContains('HIGH_VALUE_LOW_PROBABILITY', $types);
    }

    public function test_recommendations_can_be_listed_and_viewed(): void
    {
        [, , $periods] = $this->seedForecastScenario();

        $runId = $this->actingAs($this->manager(), 'sanctum')
            ->postJson('/api/v1/forecasting/run', array_merge($periods, [
                'forecast_name' => 'Recommendation Forecast',
                'simulation_count' => 100,
            ]))
            ->assertCreated()
            ->json('data.forecast_run_id');

        $list = $this->actingAs($this->manager(), 'sanctum')
            ->getJson('/api/v1/recommendations?forecast_run_id=' . $runId)
            ->assertOk()
            ->json();

        $this->assertGreaterThan(0, $list['meta']['summary']['TOTAL']);
        $this->assertSame($list['meta']['summary']['TOTAL'], $list['meta']['total']);

        $this->actingAs($this->manager(), 'sanctum')
            ->getJson('/api/v1/forecasting')
            ->assertOk()
            ->assertJsonPath('data.0.id', $runId);

        $recommendationId = $list['data'][0]['id'];

        $this->actingAs($this->manager(), 'sanctum')
            ->getJson("/api/v1/recommendations/{$recommendationId}")
            ->assertOk()
            ->assertJsonPath('data.id', $recommendationId)
            ->assertJsonStructure([
                'data' => [
                    'opportunity' => ['id', 'title', 'closing_date', 'last_activity_at'],
                ],
            ]);

        $this->actingAs($this->sales(), 'sanctum')
            ->getJson('/api/v1/recommendations')
            ->assertForbidden();
    }

    public function test_stage_changes_are_recorded_in_history(): void
    {
        $manager = $this->manager();
        $opportunity = Opportunity::factory()->create([
            'user_id' => $manager->id,
            'stage' => 'QUALIFICATION',
            'progress' => 'QUALIFICATION',
            'probability' => 30,
            'status' => OpportunityStatus::OPEN->value,
        ]);

        $opportunity->update([
            'stage' => 'NEGOTIATION',
            'progress' => 'NEGOTIATION',
            'probability' => 70,
        ]);

        $this->assertDatabaseHas('opportunity_stage_histories', [
            'opportunity_id' => $opportunity->id,
            'previous_stage' => 'QUALIFICATION',
            'current_stage' => 'NEGOTIATION',
        ]);

        $history = OpportunityStageHistory::where('opportunity_id', $opportunity->id)->first();
        $this->assertSame('30.00', $history->previous_probability);
        $this->assertSame('70.00', $history->current_probability);
    }
}
