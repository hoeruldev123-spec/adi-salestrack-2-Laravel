<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class SalesPerformanceTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    public function test_leaderboard_requires_manager_level_role(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/sales-performance/leaderboard')
            ->assertForbidden();

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/sales-performance/team-summary')
            ->assertForbidden();
    }

    public function test_leaderboard_returns_top_sales_ordered_by_revenue(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);

        $expected = [];
        foreach (range(1, 6) as $index) {
            $sales = $this->actor(Role::SALES->value);
            Opportunity::factory()->won()->create([
                'user_id' => $sales->id,
                'value' => $index * 100_000,
            ]);

            $expected[$sales->id] = $index * 100_000;
        }

        arsort($expected);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/sales-performance/leaderboard')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.win_rate', 100);

        $ids = array_map(fn (array $row) => $row['user']['id'], $response->json('data'));
        $this->assertSame(array_slice(array_keys($expected), 0, 5), $ids);
    }

    public function test_team_summary_aggregates_all_sales(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);

        $first = $this->actor(Role::SALES->value);
        $second = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(2)->create(['user_id' => $first->id, 'value' => 300_000]);
        Opportunity::factory()->lost()->create(['user_id' => $first->id]);
        Opportunity::factory()->won()->create(['user_id' => $second->id, 'value' => 100_000]);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/sales-performance/team-summary')
            ->assertOk()
            ->assertJsonPath('data.total_sales', 2)
            ->assertJsonPath('data.total_deals', 4)
            ->assertJsonPath('data.won_deals', 3)
            ->assertJsonPath('data.lost_deals', 1)
            ->assertJsonPath('data.total_revenue', 700_000)
            ->assertJsonPath('data.avg_deal_size', 233_333.33)
            ->assertJsonPath('data.overall_win_rate', 75)
            ->assertJsonStructure(['data' => ['avg_win_rate']]);
    }

    public function test_team_summary_with_no_sales_returns_zeros(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/sales-performance/team-summary')
            ->assertOk()
            ->assertJsonPath('data.total_sales', 0)
            ->assertJsonPath('data.total_revenue', 0)
            ->assertJsonPath('data.overall_win_rate', 0);
    }

    public function test_leaderboard_and_summary_require_authentication(): void
    {
        $this->getJson('/api/v1/sales-performance/leaderboard')->assertUnauthorized();
        $this->getJson('/api/v1/sales-performance/team-summary')->assertUnauthorized();
    }

    public function test_bod_can_access_performance_endpoints(): void
    {
        $bod = $this->actor(Role::MANAGEMENT->value);

        $this->actingAs($bod, 'sanctum')
            ->getJson('/api/v1/sales-performance/leaderboard')
            ->assertOk();

        $this->actingAs($bod, 'sanctum')
            ->getJson('/api/v1/sales-performance/team-summary')
            ->assertOk();
    }
}
