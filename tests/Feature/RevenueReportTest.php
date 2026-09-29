<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class RevenueReportTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    public function test_revenue_reports_require_authentication(): void
    {
        foreach (['overview', 'forecast', 'comparison'] as $endpoint) {
            $this->getJson("/api/v1/reports/revenue/{$endpoint}")->assertUnauthorized();
        }
    }

    public function test_sales_role_is_forbidden_from_revenue_reports(): void
    {
        $sales = $this->actor(Role::SALES->value);

        foreach (['overview', 'forecast', 'comparison'] as $endpoint) {
            $this->actingAs($sales, 'sanctum')
                ->getJson("/api/v1/reports/revenue/{$endpoint}")
                ->assertForbidden();
        }
    }

    public function test_overview_returns_metrics_and_breakdowns(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);
        $company = Company::factory()->create();
        $principal = MasterPrincipal::factory()->create();

        Opportunity::factory()->won()->count(2)->create([
            'user_id' => $sales->id,
            'company_id' => $company->id,
            'principal_id' => $principal->id,
            'value' => 300_000,
            'expected_close_date' => now()->toDateString(),
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'company_id' => $company->id,
            'principal_id' => $principal->id,
            'value' => 100_000,
            'expected_close_date' => now()->subMonth()->toDateString(),
            'created_at' => now()->subMonth(),
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/revenue/overview')
            ->assertOk()
            ->assertJsonPath('data.metrics.total_revenue', 700_000)
            ->assertJsonPath('data.metrics.total_deals', 3)
            ->assertJsonPath('data.metrics.avg_deal_size', 233_333.33)
            ->assertJsonCount(12, 'data.by_month')
            ->assertJsonCount(1, 'data.by_principal')
            ->assertJsonCount(1, 'data.by_account')
            ->assertJsonStructure([
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'metrics' => ['total_revenue', 'total_deals', 'avg_deal_size', 'growth_percent'],
                    'by_month' => [['month', 'deals', 'revenue']],
                ],
            ]);
    }

    public function test_overview_calculates_growth_against_previous_period(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'value' => 100_000,
            'expected_close_date' => now()->subMonth()->toDateString(),
            'created_at' => now()->subMonth(),
        ]);
        Opportunity::factory()->won()->count(3)->create([
            'user_id' => $sales->id,
            'value' => 100_000,
            'expected_close_date' => now()->toDateString(),
        ]);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/reports/revenue/overview?start_date={$start}&end_date={$end}")
            ->assertOk()
            ->assertJsonPath('data.metrics.total_revenue', 300_000)
            ->assertJsonPath('data.metrics.previous_period_revenue', 100_000)
            ->assertJsonPath('data.metrics.growth_percent', 200);
    }

    public function test_forecast_returns_weighted_values(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'value' => 1_000_000,
            'status' => 'OPEN',
            'probability' => 70,
            'expected_close_date' => now()->addMonth()->startOfMonth()->addDay(5)->toDateString(),
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'value' => 500_000,
            'probability' => 100,
        ]);
        Opportunity::factory()->lost()->create([
            'user_id' => $sales->id,
            'value' => 250_000,
            'probability' => 0,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/revenue/forecast')
            ->assertOk()
            ->assertJsonPath('data.pipeline_value', 1_000_000)
            ->assertJsonPath('data.weighted_forecast', 700_000)
            ->assertJsonPath('data.win_probability_by_status.WON', 100)
            ->assertJsonPath('data.win_probability_by_status.LOST', 0)
            ->assertJsonPath('data.win_probability_by_status.CANCELLED', 0)
            ->assertJsonCount(6, 'data.forecast_6_months')
            ->assertJsonStructure([
                'data' => [
                    'historical_win_rate',
                    'forecast_by_status' => [['status', 'deals', 'pipeline_value', 'weighted_value']],
                    'forecast_6_months' => [['month', 'pipeline_value', 'weighted_value', 'forecast_value']],
                ],
            ]);
    }

    public function test_forecast_month_uses_configured_win_rate(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);
        $closeDate = now()->startOfMonth()->addDays(5);

        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'value' => 2_000_000,
            'status' => 'OPEN',
            'probability' => 50,
            'expected_close_date' => $closeDate->toDateString(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/revenue/forecast')
            ->assertOk();

        $row = collect($response->json('data.forecast_6_months'))
            ->firstWhere('month', $closeDate->format('Y-m'));

        $this->assertNotNull($row);
        $this->assertSame(2_000_000, $row['pipeline_value']);
        $this->assertSame(1_000_000, $row['weighted_value']);
        $this->assertSame(600_000, $row['forecast_value']);
    }

    public function test_comparison_returns_monthly_quarterly_and_yoy(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(2)->create([
            'user_id' => $sales->id,
            'value' => 50_000,
            'expected_close_date' => now()->toDateString(),
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'value' => 50_000,
            'expected_close_date' => now()->subYear()->toDateString(),
            'created_at' => now()->subYear(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/revenue/comparison')
            ->assertOk()
            ->assertJsonCount(12, 'data.monthly')
            ->assertJsonPath('data.yoy.current', 100_000)
            ->assertJsonPath('data.yoy.previous', 50_000)
            ->assertJsonPath('data.yoy.growth_percent', 100)
            ->assertJsonStructure(['data' => ['previous_period' => ['revenue', 'growth_percent']]]);

        $quarterly = $response->json('data.quarterly');

        $this->assertGreaterThanOrEqual(4, count($quarterly));
        foreach ($quarterly as $row) {
            $this->assertArrayHasKey('quarter', $row);
            $this->assertArrayHasKey('revenue', $row);
            $this->assertArrayHasKey('deals', $row);
        }
    }

    public function test_overview_validates_date_range(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/revenue/overview?start_date=2026-06-01&end_date=2026-05-01')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['end_date']]);
    }

    public function test_manager_and_bod_can_access_revenue_reports(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $bod = $this->actor(Role::MANAGEMENT->value);
        $admin = $this->actor(Role::ADMIN->value);

        foreach ([$manager, $bod, $admin] as $actor) {
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/reports/revenue/overview')
                ->assertOk();
        }
    }
}
