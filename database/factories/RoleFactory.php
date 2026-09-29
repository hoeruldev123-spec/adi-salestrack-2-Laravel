<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Role as RoleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleModel>
 */
class RoleFactory extends Factory
{
    protected $model = RoleModel::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(Role::values()),
            'description' => fake()->sentence(),
        ];
    }
}
