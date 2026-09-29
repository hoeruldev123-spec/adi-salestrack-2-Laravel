<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Role as RoleModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function seedRole(string $name): RoleModel
    {
        return RoleModel::create([
            'name' => $name,
            'description' => $name,
        ]);
    }

    public function test_login_returns_token_and_user(): void
    {
        $role = $this->seedRole(Role::ADMIN->value);
        $user = User::factory()->create(['email' => 'admin@test.com', 'role_id' => $role->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@test.com')
            ->assertJsonStructure(['data' => ['token', 'user', 'token_type']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $role = $this->seedRole(Role::ADMIN->value);
        User::factory()->create(['email' => 'admin@test.com', 'role_id' => $role->id]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $role = $this->seedRole(Role::ADMIN->value);
        User::factory()->create([
            'email' => 'inactive@test.com',
            'role_id' => $role->id,
            'status' => 'INACTIVE',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@test.com',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $role = $this->seedRole(Role::ADMIN->value);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_revokes_token(): void
    {
        $role = $this->seedRole(Role::ADMIN->value);
        $user = User::factory()->create(['role_id' => $role->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
