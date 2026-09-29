<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityItem>
 */
class OpportunityItemFactory extends Factory
{
    protected $model = OpportunityItem::class;

    public function definition(): array
    {
        $product = Product::factory()->create();
        $quantity = fake()->numberBetween(1, 20);

        return [
            'opportunity_id' => Opportunity::factory(),
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'discount' => 0,
            'subtotal' => $quantity * $product->price,
        ];
    }
}
