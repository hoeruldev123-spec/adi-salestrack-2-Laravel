<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(ActivityType::values()),
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'activity_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'due_date' => fake()->dateTimeBetween('now', '+2 months'),
            'status' => fake()->randomElement(ActivityStatus::values()),
        ];
    }
}
