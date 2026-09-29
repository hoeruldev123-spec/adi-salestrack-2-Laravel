<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class SalesProfileTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    public function test_list_requires_manager_level_role(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/sales-profiles')
            ->assertForbidden();
    }

    public function test_list_returns_sales_with_performance(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);
        $inactive = $this->actor(Role::SALES->value);

        $inactive->update(['status' => 'INACTIVE']);

        Opportunity::factory()->won()->count(2)->create(['user_id' => $sales->id, 'value' => 400_000]);
        Opportunity::factory()->lost()->create(['user_id' => $sales->id]);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/sales-profiles')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sales->id)
            ->assertJsonPath('data.0.performance.won_deals', 2)
            ->assertJsonPath('data.0.performance.win_rate', 66.67)
            ->assertJsonPath('data.0.performance.total_revenue', 800_000)
            ->assertJsonPath('data.0.performance.rank', 1);
    }

    public function test_sales_can_view_own_profile_detail(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson("/api/v1/sales-profiles/{$sales->id}")
            ->assertOk()
            ->assertJsonPath('data.user.id', $sales->id)
            ->assertJsonStructure(['data' => ['user', 'performance']]);
    }

    public function test_sales_cannot_view_other_sales_profile(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $other = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson("/api/v1/sales-profiles/{$other->id}")
            ->assertForbidden();
    }

    public function test_sales_profile_detail_returns_404_for_non_sales_user(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $otherManager = $this->actor(Role::SALES_MANAGER->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/sales-profiles/{$otherManager->id}")
            ->assertNotFound();
    }

    public function test_manager_can_update_sales_profile(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/sales-profiles/{$sales->id}", [
                'name' => 'Renamed Sales',
                'email' => 'renamed@sales.test',
                'status' => 'INACTIVE',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Renamed Sales')
            ->assertJsonPath('data.user.status', 'INACTIVE');

        $this->assertDatabaseHas('users', [
            'id' => $sales->id,
            'name' => 'Renamed Sales',
            'status' => 'INACTIVE',
        ]);
    }

    public function test_sales_can_update_own_name_but_not_status(): void
    {
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->putJson("/api/v1/sales-profiles/{$sales->id}", [
                'name' => 'Self Renamed',
                'email' => $sales->email,
                'status' => 'INACTIVE',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Self Renamed')
            ->assertJsonPath('data.user.status', 'ACTIVE');

        $this->assertDatabaseHas('users', [
            'id' => $sales->id,
            'name' => 'Self Renamed',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_update_validates_email_uniqueness(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);
        $taken = $this->actor(Role::SALES->value);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/sales-profiles/{$sales->id}", [
                'name' => $sales->name,
                'email' => $taken->email,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_update_validates_required_fields(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $sales = $this->actor(Role::SALES->value);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/sales-profiles/{$sales->id}", [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['name', 'email']]);
    }

    public function test_list_ignores_non_sales_users(): void
    {
        $manager = $this->actor(Role::SALES_MANAGER->value);
        $this->actor(Role::SALES->value);
        $this->actor(Role::ADMIN->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/sales-profiles')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_user_cannot_access_profiles(): void
    {
        $this->getJson('/api/v1/sales-profiles')->assertUnauthorized();
    }
}
