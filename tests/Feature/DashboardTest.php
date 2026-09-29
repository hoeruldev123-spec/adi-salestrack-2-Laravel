<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    public function test_sales_dashboard_returns_own_metrics(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $other = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(2)->create([
            'user_id' => $sales->id,
            'value' => 500_000,
        ]);
        Opportunity::factory()->lost()->create(['user_id' => $sales->id]);
        Opportunity::factory()->count(3)->create([
            'user_id' => $sales->id,
            'value' => 200_000,
            'expected_close_date' => now()->addDays(10)->toDateString(),
        ]);
        Opportunity::factory()->count(4)->create(['user_id' => $other->id]);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/dashboard/sales')
            ->assertOk()
            ->assertJsonPath('data.total_opportunities', 6)
            ->assertJsonPath('data.won_count', 2)
            ->assertJsonPath('data.lost_count', 1)
            ->assertJsonPath('data.open_count', 3)
            ->assertJsonPath('data.total_pipeline_value', 600_000)
            ->assertJsonPath('data.won_value', 1_000_000)
            ->assertJsonPath('data.win_rate', 66.67)
            ->assertJsonCount(3, 'data.pipeline_by_status')
            ->assertJsonCount(5, 'data.recent_opportunities')
            ->assertJsonCount(3, 'data.upcoming_closings');
    }

    public function test_sales_dashboard_is_forbidden_for_manager(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/dashboard/sales')
            ->assertForbidden();
    }

    public function test_manager_dashboard_requires_manager_role(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/dashboard/manager')
            ->assertForbidden();
    }

    public function test_manager_dashboard_returns_company_metrics(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $rich = $this->actor(Role::SALES->value);
        $poor = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(2)->create(['user_id' => $rich->id, 'value' => 1_000_000]);
        Opportunity::factory()->lost()->create(['user_id' => $rich->id]);
        Opportunity::factory()->create(['user_id' => $poor->id, 'value' => 100_000]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/dashboard/manager')
            ->assertOk()
            ->assertJsonPath('data.total_opportunities', 4)
            ->assertJsonPath('data.total_revenue', 2_000_000)
            ->assertJsonPath('data.win_rate', 66.67)
            ->assertJsonPath('data.active_sales_count', 2)
            ->assertJsonCount(2, 'data.team_performance')
            ->assertJsonPath('data.team_performance.0.user.id', $rich->id)
            ->assertJsonPath('data.team_performance.0.rank', 1)
            ->assertJsonPath('data.top_performer.user.id', $rich->id)
            ->assertJsonCount(6, 'data.monthly_performance')
            ->assertJsonStructure([
                'data' => [
                    'pipeline_by_status',
                    'principal_performance',
                    'monthly_performance' => [['month', 'opportunities', 'revenue']],
                ],
            ]);

        $this->assertSame(2, $response->json('data.team_performance.0.won_deals'));
        $this->assertGreaterThanOrEqual(0, $response->json('data.total_opportunities'));
    }

    public function test_manager_dashboard_is_forbidden_for_sales(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/dashboard/manager')
            ->assertForbidden();
    }

    public function test_bod_dashboard_returns_executive_summary(): void
    {
        $bod = $this->actor(Role::MANAGEMENT->value);
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(2)->create(['user_id' => $sales->id, 'value' => 750_000]);
        Opportunity::factory()->create(['user_id' => $sales->id, 'value' => 250_000]);

        $this->actingAs($bod, 'sanctum')
            ->getJson('/api/v1/dashboard/bod')
            ->assertOk()
            ->assertJsonPath('data.summary.total_revenue', 1_500_000)
            ->assertJsonPath('data.summary.total_pipeline', 250_000)
            ->assertJsonPath('data.summary.active_sales_count', 1)
            ->assertJsonCount(12, 'data.revenue_trend')
            ->assertJsonCount(6, 'data.forecast_6_months')
            ->assertJsonCount(1, 'data.top_sales');
    }

    public function test_bod_dashboard_is_forbidden_for_sales(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/dashboard/bod')
            ->assertForbidden();
    }

    public function test_admin_can_access_management_dashboards(): void
    {
        $admin = $this->actor(Role::ADMIN->value);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/dashboard/manager')
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/dashboard/bod')
            ->assertOk();
    }

    public function test_dashboards_require_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/sales')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/manager')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/bod')->assertUnauthorized();
    }
}
