<?php

namespace Database\Factories;

use App\Models\ContractType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractType>
 */
class ContractTypeFactory extends Factory
{
    protected $model = ContractType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
