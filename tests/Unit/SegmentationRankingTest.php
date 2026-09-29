<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use App\Services\Report\RankingService;
use App\Support\Periods;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class SegmentationRankingTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    private function periods(): object
    {
        return new class
        {
            use Periods;

            public function segmentOf(float $revenue): string
            {
                return $this->segment($revenue);
            }

            public function growth(float $current, float $previous): ?float
            {
                return $this->growthPercent($current, $previous);
            }

            public function monthKeysBetween(string $start, string $end): array
            {
                return $this->monthKeys($start, $end);
            }

            public function previousKeys(array $keys): array
            {
                return $this->previousPeriodKeys($keys);
            }
        };
    }

    public function test_value_segmentation_thresholds(): void
    {
        $periods = $this->periods();

        $this->assertSame('platinum', $periods->segmentOf(5_000_000_000));
        $this->assertSame('platinum', $periods->segmentOf(9_000_000_000));
        $this->assertSame('gold', $periods->segmentOf(4_999_999_999));
        $this->assertSame('gold', $periods->segmentOf(1_000_000_000));
        $this->assertSame('silver', $periods->segmentOf(999_999_999));
        $this->assertSame('silver', $periods->segmentOf(500_000_000));
        $this->assertSame('bronze', $periods->segmentOf(499_999_999));
        $this->assertSame('bronze', $periods->segmentOf(0));
    }

    public function test_growth_percent_calculation(): void
    {
        $periods = $this->periods();

        $this->assertSame(100.0, $periods->growth(200, 100));
        $this->assertSame(-50.0, $periods->growth(50, 100));
        $this->assertSame(0.0, $periods->growth(100, 100));
        $this->assertNull($periods->growth(100, 0));
        $this->assertSame(0.0, $periods->growth(0, 0));
    }

    public function test_month_keys_covers_range_inclusively(): void
    {
        $keys = $this->periods()->monthKeysBetween('2026-01-15', '2026-04-02');

        $this->assertSame(['2026-01', '2026-02', '2026-03', '2026-04'], $keys);
    }

    public function test_previous_period_keys_shift_by_span(): void
    {
        $previous = $this->periods()->previousKeys(['2026-03', '2026-04']);

        $this->assertSame(['2026-01', '2026-02'], $previous);
    }

    public function test_account_ranking_orders_by_revenue_with_segment(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $platinum = Company::factory()->create();
        $bronze = Company::factory()->create();

        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'company_id' => $platinum->id,
            'value' => 7_000_000_000,
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'company_id' => $bronze->id,
            'value' => 1_000_000,
        ]);

        $result = app(RankingService::class)->accounts([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        $this->assertSame($platinum->id, $result['data'][0]['account']['id']);
        $this->assertSame(1, $result['data'][0]['rank']);
        $this->assertSame('platinum', $result['data'][0]['segment']);
        $this->assertSame(7_000_000_000.0, $result['data'][0]['total_revenue']);
        $this->assertSame('bronze', $result['data'][1]['segment']);
    }

    public function test_sales_ranking_calculates_pipeline_velocity(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        Opportunity::factory()->won()->create(['user_id' => $sales->id, 'value' => 100_000]);
        Opportunity::factory()->lost()->create(['user_id' => $sales->id, 'value' => 100_000]);
        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'status' => 'OPEN',
            'value' => 300_000,
            'probability' => 50,
        ]);

        $result = app(RankingService::class)->sales([
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $row = collect($result['data'])->firstWhere('user.id', $sales->id);
        $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;

        $this->assertSame(3, $row['total_deals']);
        $this->assertSame(1, $row['won_deals']);
        $this->assertSame(50.0, $row['win_rate']);
        $this->assertSame(100_000.0, $row['total_revenue']);
        $this->assertSame(100_000.0, $row['avg_deal_size']);
        $this->assertSame(300_000.0, $row['pipeline_value']);
        $this->assertSame(round(150_000 / $days, 2), $row['pipeline_velocity']);
    }

    public function test_principal_ranking_calculates_penetration(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $principal = MasterPrincipal::factory()->create();

        Company::factory()->count(4)->create();

        $wonCompanies = Company::all()->take(2);
        foreach ($wonCompanies as $company) {
            Opportunity::factory()->won()->create([
                'user_id' => $sales->id,
                'principal_id' => $principal->id,
                'company_id' => $company->id,
                'value' => 250_000,
            ]);
        }

        $result = app(RankingService::class)->principals([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        $row = collect($result['data'])->firstWhere('principal.id', $principal->id);

        $this->assertSame(1, $row['rank']);
        $this->assertSame(500_000.0, $row['total_revenue']);
        $this->assertSame(2, $row['won_accounts']);
        $this->assertSame(50.0, $row['account_penetration']);
        $this->assertSame(100.0, $row['win_rate']);
    }

    public function test_sales_ranking_growth_against_previous_period(): void
    {
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'value' => 400_000,
            'created_at' => now()->startOfMonth()->subMonth(),
        ]);
        Opportunity::factory()->won()->create([
            'user_id' => $sales->id,
            'value' => 600_000,
            'created_at' => now()->startOfMonth(),
        ]);

        $result = app(RankingService::class)->sales([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        $row = collect($result['data'])->firstWhere('user.id', $sales->id);

        $this->assertSame(600_000.0, $row['total_revenue']);
        $this->assertSame(50.0, $row['growth_percent']);
    }
}
