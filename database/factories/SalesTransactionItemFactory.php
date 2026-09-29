<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesTransactionItem>
 */
class SalesTransactionItemFactory extends Factory
{
    protected $model = SalesTransactionItem::class;

    public function definition(): array
    {
        $product = Product::factory()->create();
        $quantity = fake()->numberBetween(1, 20);

        return [
            'sales_transaction_id' => SalesTransaction::factory(),
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'subtotal' => $quantity * $product->price,
        ];
    }
}
