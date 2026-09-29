<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Role as RoleModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_role(): void
    {
        $role = RoleModel::create(['name' => Role::ADMIN->value, 'description' => 'Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertInstanceOf(RoleModel::class, $user->role);
        $this->assertSame(Role::ADMIN->value, $user->role->name);
    }

    public function test_has_role_helper(): void
    {
        $role = RoleModel::create(['name' => Role::SALES_MANAGER->value, 'description' => 'Manager']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($user->hasRole(Role::SALES_MANAGER->value));
        $this->assertFalse($user->hasRole(Role::ADMIN->value));
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
