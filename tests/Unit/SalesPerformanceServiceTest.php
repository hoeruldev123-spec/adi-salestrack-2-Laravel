<?php

namespace Tests\Unit;

use App\Enums\OpportunityStatus;
use App\Enums\Role;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Sales\SalesPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class SalesPerformanceServiceTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    private function service(): SalesPerformanceService
    {
        return app(SalesPerformanceService::class);
    }

    public function test_summarize_calculates_win_rate_and_revenue(): void
    {
        $summary = $this->service()->summarize(collect([
            ['status' => OpportunityStatus::WON->value, 'value' => 300],
            ['status' => OpportunityStatus::WON->value, 'value' => 100],
            ['status' => OpportunityStatus::LOST->value, 'value' => 50],
            ['status' => OpportunityStatus::OPEN->value, 'value' => 70],
        ]));

        $this->assertSame(4, $summary['total_deals']);
        $this->assertSame(2, $summary['won_deals']);
        $this->assertSame(1, $summary['lost_deals']);
        $this->assertSame(66.67, $summary['win_rate']);
        $this->assertSame(400.0, $summary['total_revenue']);
        $this->assertSame(200.0, $summary['avg_deal_size']);
    }

    public function test_summarize_returns_zero_win_rate_without_closed_deals(): void
    {
        $summary = $this->service()->summarize(collect([
            ['status' => OpportunityStatus::OPEN->value, 'value' => 100],
        ]));

        $this->assertSame(0.0, $summary['win_rate']);
        $this->assertSame(0.0, $summary['total_revenue']);
        $this->assertSame(0.0, $summary['avg_deal_size']);
        $this->assertSame(0, $summary['won_deals']);
    }

    public function test_for_user_only_counts_own_opportunities(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $other = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(2)->create(['user_id' => $sales->id, 'value' => 150]);
        Opportunity::factory()->won()->count(5)->create(['user_id' => $other->id, 'value' => 900]);

        $performance = $this->service()->forUser($sales);

        $this->assertSame(2, $performance['total_deals']);
        $this->assertSame(300.0, $performance['total_revenue']);
        $this->assertSame(100.0, $performance['win_rate']);
    }

    public function test_for_users_ranks_by_revenue_then_win_rate(): void
    {
        $top = $this->actor(Role::SALES->value);
        $bottom = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->create(['user_id' => $top->id, 'value' => 1_000]);
        Opportunity::factory()->won()->create(['user_id' => $bottom->id, 'value' => 500]);

        $rows = $this->service()->forUsers(User::whereIn('id', [$top->id, $bottom->id])->get());

        $this->assertSame($top->id, $rows[0]['user']->id);
        $this->assertSame(1, $rows[0]['performance']['rank']);
        $this->assertSame($bottom->id, $rows[1]['user']->id);
        $this->assertSame(2, $rows[1]['performance']['rank']);
    }

    public function test_for_users_gives_equal_rank_to_zero_revenue_sales(): void
    {
        $first = $this->actor(Role::SALES->value);
        $second = $this->actor(Role::SALES->value);

        $rows = $this->service()->forUsers(User::whereIn('id', [$first->id, $second->id])->orderBy('id')->get());

        $this->assertSame(1, $rows[0]['performance']['rank']);
        $this->assertSame(2, $rows[1]['performance']['rank']);
        $this->assertSame(0.0, $rows[0]['performance']['win_rate']);
    }

    public function test_team_summary_aggregates_metrics(): void
    {
        $first = $this->actor(Role::SALES->value);
        $second = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(2)->create(['user_id' => $first->id, 'value' => 400]);
        Opportunity::factory()->lost()->create(['user_id' => $first->id]);
        Opportunity::factory()->won()->create(['user_id' => $second->id, 'value' => 200]);

        $summary = $this->service()->teamSummary();

        $this->assertSame(2, $summary['total_sales']);
        $this->assertSame(4, $summary['total_deals']);
        $this->assertSame(3, $summary['won_deals']);
        $this->assertSame(1, $summary['lost_deals']);
        $this->assertSame(1_000.0, $summary['total_revenue']);
        $this->assertSame(75.0, $summary['overall_win_rate']);
        $this->assertSame(333.33, $summary['avg_deal_size']);
    }

    public function test_leaderboard_limits_results(): void
    {
        foreach (range(1, 7) as $index) {
            $sales = $this->actor(Role::SALES->value);
            Opportunity::factory()->won()->create(['user_id' => $sales->id, 'value' => $index * 100]);
        }

        $leaderboard = $this->service()->leaderboard(5);

        $this->assertCount(5, $leaderboard);
        $this->assertSame(700.0, $leaderboard[0]['total_revenue']);
        $this->assertSame(1, $leaderboard[0]['rank']);
    }
}
