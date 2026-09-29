<?php

namespace Tests\Concerns;

use App\Models\Role as RoleModel;
use App\Models\User;

trait CreatesActors
{
    private function actor(string $role): User
    {
        $roleModel = RoleModel::firstOrCreate(['name' => $role], ['description' => $role]);

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
