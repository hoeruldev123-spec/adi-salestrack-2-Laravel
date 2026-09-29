<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Opportunity;
use App\Services\Report\RevenueReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class ForecastReportTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    private function service(): RevenueReportService
    {
        return app(RevenueReportService::class);
    }

    public function test_weighted_forecast_uses_opportunity_probability(): void
    {
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'status' => 'OPEN',
            'value' => 1_000_000,
            'probability' => 70,
            'expected_close_date' => now()->addWeek()->toDateString(),
        ]);
        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'status' => 'OPEN',
            'value' => 500_000,
            'probability' => 30,
            'expected_close_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $forecast = $this->service()->forecast();

        $this->assertSame(1_500_000.0, $forecast['pipeline_value']);
        $this->assertSame(850_000.0, $forecast['weighted_forecast']);
        $this->assertSame(0.3, $forecast['historical_win_rate']);
    }

    public function test_win_probability_per_status(): void
    {
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->create(['user_id' => $sales->id, 'value' => 100_000]);
        Opportunity::factory()->lost()->create(['user_id' => $sales->id, 'value' => 100_000]);
        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'status' => 'CANCELLED',
            'value' => 100_000,
            'probability' => 80,
        ]);
        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'status' => 'OPEN',
            'value' => 200_000,
            'probability' => 60,
        ]);
        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'status' => 'OPEN',
            'value' => 100_000,
            'probability' => 40,
        ]);

        $forecast = $this->service()->forecast();

        $this->assertSame(100.0, $forecast['win_probability_by_status']['WON']);
        $this->assertSame(0.0, $forecast['win_probability_by_status']['LOST']);
        $this->assertSame(0.0, $forecast['win_probability_by_status']['CANCELLED']);
        $this->assertSame(53.33, $forecast['win_probability_by_status']['OPEN']);
    }

    public function test_forecast_by_status_only_contains_active_pipeline(): void
    {
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'status' => 'OPEN',
            'value' => 400_000,
            'probability' => 50,
        ]);
        Opportunity::factory()->won()->create(['user_id' => $sales->id, 'value' => 900_000]);
        Opportunity::factory()->lost()->create(['user_id' => $sales->id, 'value' => 900_000]);

        $forecast = $this->service()->forecast();

        $statuses = array_column($forecast['forecast_by_status'], 'status');
        $this->assertSame(['OPEN', 'ON_HOLD'], $statuses);
        $this->assertSame(400_000.0, $forecast['forecast_by_status'][0]['pipeline_value']);
        $this->assertSame(200_000.0, $forecast['forecast_by_status'][0]['weighted_value']);
        $this->assertSame(400_000.0, $forecast['pipeline_value']);
        $this->assertSame(200_000.0, $forecast['weighted_forecast']);
    }

    public function test_forecast_six_months_groups_pipeline_by_closing_month(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $closeDate = now()->startOfMonth()->addDays(3);

        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'status' => 'OPEN',
            'value' => 2_000_000,
            'probability' => 50,
            'expected_close_date' => $closeDate->toDateString(),
        ]);

        $forecast = $this->service()->forecast();

        $this->assertCount(6, $forecast['forecast_6_months']);
        $this->assertSame(
            array_map(fn ($i) => now()->startOfMonth()->addMonths($i)->format('Y-m'), range(0, 5)),
            array_column($forecast['forecast_6_months'], 'month')
        );

        $row = collect($forecast['forecast_6_months'])->firstWhere('month', $closeDate->format('Y-m'));

        $this->assertSame(1, $row['deals']);
        $this->assertSame(2_000_000.0, $row['pipeline_value']);
        $this->assertSame(1_000_000.0, $row['weighted_value']);
        $this->assertSame(600_000.0, $row['forecast_value']);
    }

    public function test_forecast_excludes_closed_opportunities_from_pipeline(): void
    {
        $sales = $this->actor(Role::SALES->value);

        Opportunity::factory()->won()->count(3)->create(['user_id' => $sales->id, 'value' => 500_000]);
        Opportunity::factory()->lost()->count(2)->create(['user_id' => $sales->id, 'value' => 500_000]);
        Opportunity::factory()->create([
            'user_id' => $sales->id,
            'status' => 'OPEN',
            'value' => 100_000,
            'probability' => 10,
        ]);

        $forecast = $this->service()->forecast();

        $this->assertSame(100_000.0, $forecast['pipeline_value']);
        $this->assertSame(10_000.0, $forecast['weighted_forecast']);
    }

    public function test_historical_win_rate_is_configurable(): void
    {
        config(['salestrack.forecast.historical_win_rate' => 0.45]);

        $forecast = $this->service()->forecast();

        $this->assertSame(0.45, $forecast['historical_win_rate']);
    }
}
