<?php

namespace Database\Factories;

use App\Models\MasterPrincipal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MasterPrincipal>
 */
class MasterPrincipalFactory extends Factory
{
    protected $model = MasterPrincipal::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'website' => fake()->url(),
            'status' => 'ACTIVE',
        ];
    }
}
