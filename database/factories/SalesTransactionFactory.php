<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesTransaction>
 */
class SalesTransactionFactory extends Factory
{
    protected $model = SalesTransaction::class;

    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'transaction_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'transaction_number' => 'STX-'.date('Y').'-'.fake()->unique()->numberBetween(100000, 999999),
            'total_amount' => fake()->numberBetween(5_000_000, 1_000_000_000),
            'status' => 'PAID',
        ];
    }
}
