<?php

namespace Database\Factories;

use App\Enums\WorkflowStatusGroup;
use App\Models\QuoteSystemStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A custom (non-system) quote system status (spec 0404 part A).
 *
 * @extends Factory<QuoteSystemStatus>
 */
class QuoteSystemStatusFactory extends Factory
{
    protected $model = QuoteSystemStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(['slate', 'green', 'red', 'blue']),
            'sort_order' => fake()->numberBetween(11, 99),
            'behavior' => WorkflowStatusGroup::Pending,
        ];
    }
}
