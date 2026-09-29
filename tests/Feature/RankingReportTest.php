<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class RankingReportTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    public function test_ranking_reports_require_authentication(): void
    {
        foreach (['sales', 'principals', 'accounts'] as $endpoint) {
            $this->getJson("/api/v1/reports/ranking/{$endpoint}")->assertUnauthorized();
        }
    }

    public function test_sales_role_is_forbidden_from_ranking_reports(): void
    {
        $sales = $this->actor(Role::SALES->value);

        foreach (['sales', 'principals', 'accounts'] as $endpoint) {
            $this->actingAs($sales, 'sanctum')
                ->getJson("/api/v1/reports/ranking/{$endpoint}")
                ->assertForbidden();
        }
    }

    public function test_sales_ranking_orders_by_revenue(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $top = $this->actor(Role::SALES->value);
        $bottom = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(2)->create(['user_id' => $top->id, 'value' => 500_000]);
        Opportunity::factory()->lost()->create(['user_id' => $top->id, 'value' => 10_000]);
        Opportunity::factory()->won()->create(['user_id' => $bottom->id, 'value' => 100_000]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/ranking/sales')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'data' => [[
                        'rank',
                        'user',
                        'total_deals',
                        'won_deals',
                        'lost_deals',
                        'win_rate',
                        'total_revenue',
                        'avg_deal_size',
                        'pipeline_value',
                        'pipeline_velocity',
                        'growth_percent',
                    ]],
                ],
            ]);

        $rows = $response->json('data.data');
        $this->assertSame($top->id, $rows[0]['user']['id']);
        $this->assertSame(1, $rows[0]['rank']);
        $this->assertSame(1_000_000, $rows[0]['total_revenue']);
        $this->assertSame(66.67, $rows[0]['win_rate']);
        $this->assertSame($bottom->id, $rows[1]['user']['id']);
        $this->assertSame(2, $rows[1]['rank']);
    }

    public function test_sales_ranking_calculates_growth_against_previous_period(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'value' => 50_000,
            'created_at' => now()->subMonth(),
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'value' => 100_000,
            'created_at' => now(),
        ]);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/reports/ranking/sales?start_date={$start}&end_date={$end}")
            ->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('user.id', $sales->id);

        $this->assertSame(100_000, $row['total_revenue']);
        $this->assertSame(100, $row['growth_percent']);
    }

    public function test_principal_ranking_returns_penetration_and_growth(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);
        $principal = MasterPrincipal::factory()->create();
        $company = Company::factory()->create();

        Opportunity::factory()->won()->count(2)->create([
            'user_id' => $sales->id,
            'principal_id' => $principal->id,
            'company_id' => $company->id,
            'value' => 250_000,
        ]);
        Company::factory()->count(2)->create();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/ranking/principals')
            ->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('principal.id', $principal->id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['rank']);
        $this->assertSame(500_000, $row['total_revenue']);
        $this->assertSame(100, $row['win_rate']);
        $this->assertSame(1, $row['won_accounts']);
        $this->assertSame(33.33, $row['account_penetration']);
    }

    public function test_account_ranking_applies_value_segmentation(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);

        $platinum = Company::factory()->create();
        $gold = Company::factory()->create();
        $silver = Company::factory()->create();
        $bronze = Company::factory()->create();

        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'company_id' => $platinum->id,
            'value' => 6_000_000_000,
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'company_id' => $gold->id,
            'value' => 2_000_000_000,
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'company_id' => $silver->id,
            'value' => 600_000_000,
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'company_id' => $bronze->id,
            'value' => 100_000,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/ranking/accounts')
            ->assertOk();

        $rows = collect($response->json('data.data'));

        $this->assertSame('platinum', $rows->firstWhere('account.id', $platinum->id)['segment']);
        $this->assertSame('gold', $rows->firstWhere('account.id', $gold->id)['segment']);
        $this->assertSame('silver', $rows->firstWhere('account.id', $silver->id)['segment']);
        $this->assertSame('bronze', $rows->firstWhere('account.id', $bronze->id)['segment']);
        $this->assertSame(1, $rows->firstWhere('account.id', $platinum->id)['rank']);
        $this->assertSame(6_000_000_000, $rows->firstWhere('account.id', $platinum->id)['total_revenue']);
    }

    public function test_account_ranking_includes_growth_potential_from_open_pipeline(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);
        $company = Company::factory()->create();

        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'company_id' => $company->id,
            'value' => 750_000,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/ranking/accounts')
            ->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('account.id', $company->id);

        $this->assertSame(750_000, $row['growth_potential']);
        $this->assertSame(0, $row['total_revenue']);
        $this->assertSame('bronze', $row['segment']);
    }

    public function test_ranking_reports_accept_custom_period(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'value' => 100_000,
            'created_at' => now()->subMonths(3),
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'value' => 200_000,
            'created_at' => now(),
        ]);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/reports/ranking/sales?start_date={$start}&end_date={$end}")
            ->assertOk()
            ->assertJsonPath('data.period.start_date', $start)
            ->assertJsonPath('data.period.end_date', $end);

        $row = collect($response->json('data.data'))->firstWhere('user.id', $sales->id);

        $this->assertSame(200_000, $row['total_revenue']);
        $this->assertSame(1, $row['total_deals']);
    }

    public function test_ranking_reports_are_accessible_for_bod_and_admin(): void
    {
        $bod = $this->actor(Role::MANAGEMENT->value);
        $admin = $this->actor(Role::ADMIN->value);

        foreach ([$bod, $admin] as $actor) {
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/reports/ranking/sales')
                ->assertOk();
        }
    }
}
