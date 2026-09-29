<?php

namespace Tests\Feature;

use App\Enums\OpportunityStatus;
use App\Enums\Role;
use App\Models\Opportunity;
use App\Models\OpportunityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    private function logFor(Opportunity $opportunity, ?string $field = null, ?string $description = null): void
    {
        OpportunityLog::forceCreate([
            'opportunity_id' => $opportunity->id,
            'user_id' => $opportunity->user_id,
            'field_name' => $field,
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    public function test_reports_require_authentication(): void
    {
        foreach (['activity', 'performance', 'pipeline', 'export'] as $endpoint) {
            $this->getJson("/api/v1/reports/sales/{$endpoint}")->assertUnauthorized();
        }
    }

    public function test_sales_can_view_own_activity_report(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $opportunity = Opportunity::factory()->create(['user_id' => $sales->id]);

        $this->logFor($opportunity, null, 'Opportunity created');
        $this->logFor($opportunity, 'title');
        $this->logFor($opportunity, 'status');

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/reports/sales/activity')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.user.id', $sales->id)
            ->assertJsonPath('data.data.0.created', 1)
            ->assertJsonPath('data.data.0.updated', 1)
            ->assertJsonPath('data.data.0.status_updated', 1)
            ->assertJsonPath('data.data.0.total', 3)
            ->assertJsonStructure(['data' => ['period' => ['start_date', 'end_date']]]);
    }

    public function test_sales_cannot_view_other_sales_report(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $other = $this->actor(Role::SALES->value);

        foreach (['activity', 'performance', 'pipeline', 'export'] as $endpoint) {
            $this->actingAs($sales, 'sanctum')
                ->getJson("/api/v1/reports/sales/{$endpoint}?sales_id={$other->id}")
                ->assertForbidden();
        }
    }

    public function test_manager_can_filter_report_by_sales(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);
        $other = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(2)->create(['user_id' => $sales->id, 'value' => 100_000]);
        Opportunity::factory()->lost()->create(['user_id' => $other->id, 'value' => 50_000]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/reports/sales/performance?sales_id={$sales->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.user.id', $sales->id)
            ->assertJsonPath('data.data.0.won_deals', 2)
            ->assertJsonPath('data.data.0.win_rate', 100)
            ->assertJsonPath('data.data.0.total_revenue', 200_000);
    }

    public function test_performance_report_calculates_win_rate(): void
    {
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(1)->create(['user_id' => $sales->id, 'value' => 400_000]);
        Opportunity::factory()->lost()->count(1)->create(['user_id' => $sales->id, 'value' => 10_000]);
        Opportunity::factory()->count(2)->create(['user_id' => $sales->id]);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/reports/sales/performance')
            ->assertOk()
            ->assertJsonPath('data.data.0.total_deals', 4)
            ->assertJsonPath('data.data.0.won_deals', 1)
            ->assertJsonPath('data.data.0.lost_deals', 1)
            ->assertJsonPath('data.data.0.win_rate', 50)
            ->assertJsonPath('data.data.0.total_revenue', 400_000)
            ->assertJsonPath('data.data.0.avg_deal_size', 400_000);
    }

    public function test_performance_report_defaults_to_current_month(): void
    {
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);
        Opportunity::factory()->won()->create(['user_id' => $sales->id]);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/reports/sales/performance')
            ->assertOk()
            ->assertJsonPath('data.period.start_date', now()->startOfMonth()->toDateString())
            ->assertJsonPath('data.period.end_date', now()->endOfMonth()->toDateString())
            ->assertJsonPath('data.data.0.won_deals', 1);
    }

    public function test_pipeline_report_groups_by_status(): void
    {
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->count(3)->create(['user_id' => $sales->id, 'value' => 10_000]);
        Opportunity::factory()->won()->count(2)->create(['user_id' => $sales->id, 'value' => 20_000]);
        Opportunity::factory()->lost()->create(['user_id' => $sales->id, 'value' => 30_000]);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/reports/sales/pipeline')
            ->assertOk()
            ->assertJsonPath('data.total_count', 6)
            ->assertJsonPath('data.total_value', 100_000)
            ->assertJsonCount(count(OpportunityStatus::cases()), 'data.data');
    }

    public function test_pipeline_report_filters_by_status(): void
    {
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->count(2)->create(['user_id' => $sales->id]);
        Opportunity::factory()->won()->create(['user_id' => $sales->id]);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/reports/sales/pipeline?status='.OpportunityStatus::WON->value)
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.status', OpportunityStatus::WON->value)
            ->assertJsonPath('data.data.0.count', 1);
    }

    public function test_export_returns_csv_stream(): void
    {
        $sales = $this->actor(Role::SALES->value);
        Opportunity::factory()->won()->create(['user_id' => $sales->id, 'value' => 100_000]);

        $response = $this->actingAs($sales, 'sanctum')
            ->get('/api/v1/reports/sales/export');

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sales_id,sales_name,sales_email', $response->getContent());
        $this->assertStringContainsString($sales->email, $response->getContent());
    }

    public function test_report_validates_date_range(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/sales/performance?start_date=2026-05-10&end_date=2026-05-01')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['end_date']]);
    }

    public function test_report_validates_sales_id(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/sales/performance?sales_id=999999')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['sales_id']]);
    }

    public function test_report_validates_status_value(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/sales/pipeline?status=INVALID')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_activity_report_respects_period_filter(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $opportunity = Opportunity::factory()->create(['user_id' => $sales->id]);

        OpportunityLog::forceCreate([
            'opportunity_id' => $opportunity->id,
            'user_id' => $sales->id,
            'description' => 'Opportunity created',
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/reports/sales/activity?start_date='.now()->toDateString().'&end_date='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
    }
}
