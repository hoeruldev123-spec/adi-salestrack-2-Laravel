<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_show_returns_own_profile(): void
    {
        $user = $this->actor(Role::ADMIN->value);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role.name', Role::ADMIN->value)
            ->assertJsonMissingPath('data.performance');
    }

    public function test_show_includes_performance_for_sales(): void
    {
        $sales = $this->actor(Role::SALES->value);
        Opportunity::factory()->won()->count(2)->create(['user_id' => $sales->id]);
        Opportunity::factory()->lost()->count(2)->create(['user_id' => $sales->id]);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.performance.won_deals', 2)
            ->assertJsonPath('data.performance.lost_deals', 2)
            ->assertJsonPath('data.performance.win_rate', 50);
    }

    public function test_update_profile_changes_own_data(): void
    {
        $user = $this->actor(Role::SALES->value);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'email' => 'updated@test.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'updated@test.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@test.com',
        ]);
    }

    public function test_update_profile_can_change_password(): void
    {
        $user = $this->actor(Role::SALES->value);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'new-secret-password',
            ])
            ->assertOk();

        $this->assertTrue(
            Hash::check('new-secret-password', $user->fresh()->password)
        );
    }

    public function test_update_profile_validates_email(): void
    {
        $user = $this->actor(Role::SALES->value);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'Valid Name',
                'email' => 'not-an-email',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_update_profile_rejects_duplicate_email(): void
    {
        $user = $this->actor(Role::SALES->value);
        $other = $this->actor(Role::SALES->value);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => $user->name,
                'email' => $other->email,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_update_profile_requires_name(): void
    {
        $user = $this->actor(Role::SALES->value);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', ['email' => $user->email])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_sales_performance_ignores_other_sales_opportunities(): void
    {
        $sales = $this->actor(Role::SALES->value);
        $other = $this->actor(Role::SALES->value);
        Opportunity::factory()->won()->count(3)->create(['user_id' => $other->id]);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.performance.total_deals', 0)
            ->assertJsonPath('data.performance.total_revenue', 0);
    }
}
