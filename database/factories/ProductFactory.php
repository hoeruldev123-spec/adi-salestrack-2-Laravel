<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\MasterPrincipal;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'principal_id' => MasterPrincipal::factory(),
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->unique()->lexify('???-####')),
            'type' => fake()->randomElement(ProductType::values()),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(1_000_000, 500_000_000),
            'status' => 'ACTIVE',
        ];
    }
}
