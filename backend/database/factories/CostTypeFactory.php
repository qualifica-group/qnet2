<?php

namespace Database\Factories;

use App\Models\CostType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostType>
 */
class CostTypeFactory extends Factory
{
    protected $model = CostType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(10, 99),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
