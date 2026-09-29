<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Opportunity;
use App\Models\OpportunityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    public function test_activity_logs_require_manager_level_role(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/activity-logs')
            ->assertForbidden();
    }

    public function test_activity_logs_require_authentication(): void
    {
        $this->getJson('/api/v1/activity-logs')->assertUnauthorized();
    }

    public function test_manager_sees_paginated_logs_newest_first(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);

        $opportunity = Opportunity::factory()->create(['user_id' => $sales->id]);

        OpportunityLog::forceCreate([
            'opportunity_id' => $opportunity->id,
            'user_id' => $sales->id,
            'description' => 'Opportunity created',
            'created_at' => now()->subDay(),
        ]);
        OpportunityLog::forceCreate([
            'opportunity_id' => $opportunity->id,
            'user_id' => $sales->id,
            'field_name' => 'status',
            'old_value' => 'OPEN',
            'new_value' => 'WON',
            'created_at' => now(),
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/activity-logs')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'user', 'opportunity', 'field_name', 'description', 'created_at']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('data.0.field_name', 'status')
            ->assertJsonPath('data.1.description', 'Opportunity created')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_activity_logs_are_paginated(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);
        $opportunity = Opportunity::factory()->create(['user_id' => $sales->id]);

        foreach (range(1, 3) as $index) {
            OpportunityLog::forceCreate([
                'opportunity_id' => $opportunity->id,
                'user_id' => $sales->id,
                'field_name' => 'title',
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/activity-logs?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_activity_logs_can_filter_by_user(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $first = $this->actor(Role::SALES->value);
        $second = $this->actor(Role::SALES->value);

        $firstOpportunity = Opportunity::factory()->create(['user_id' => $first->id]);
        $secondOpportunity = Opportunity::factory()->create(['user_id' => $second->id]);

        OpportunityLog::create([
            'opportunity_id' => $firstOpportunity->id,
            'user_id' => $first->id,
            'description' => 'Opportunity created',
        ]);
        OpportunityLog::create([
            'opportunity_id' => $secondOpportunity->id,
            'user_id' => $second->id,
            'description' => 'Opportunity created',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/activity-logs?user_id={$first->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $first->id);
    }

    public function test_bod_and_admin_can_access_activity_logs(): void
    {
        $bod = $this->actor(Role::MANAGEMENT->value);
        $admin = $this->actor(Role::ADMIN->value);

        foreach ([$bod, $admin] as $actor) {
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/activity-logs')
                ->assertOk();
        }
    }

    public function test_active_sales_users_endpoint(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $active = $this->actor(Role::SALES->value);
        $inactive = $this->actor(Role::SALES->value);
        $inactive->update(['status' => 'INACTIVE']);
        $this->actor(Role::ADMIN->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/users/sales')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_active_sales_users_endpoint_is_forbidden_for_sales(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/users/sales')
            ->assertForbidden();
    }
}
