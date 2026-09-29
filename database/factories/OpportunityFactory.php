<?php

namespace Database\Factories;

use App\Enums\OpportunityStage;
use App\Enums\OpportunityStatus;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        $stage = fake()->randomElement(OpportunityStage::cases());

        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'principal_id' => MasterPrincipal::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'value' => fake()->numberBetween(10_000_000, 2_000_000_000),
            'stage' => $stage->value,
            'status' => OpportunityStatus::OPEN->value,
            'probability' => $stage->defaultProbability(),
            'expected_close_date' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'source' => fake()->randomElement(['WEB', 'REFERRAL', 'CALL', 'EMAIL', 'EVENT', 'OTHER']),
        ];
    }

    public function won(): static
    {
        return $this->state(fn () => [
            'stage' => OpportunityStage::CLOSING->value,
            'status' => OpportunityStatus::WON->value,
            'probability' => 100,
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => [
            'status' => OpportunityStatus::LOST->value,
            'probability' => 0,
        ]);
    }
}
