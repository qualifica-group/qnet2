<?php

namespace Database\Factories;

use App\Models\AbsenceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbsenceType>
 */
class AbsenceTypeFactory extends Factory
{
    protected $model = AbsenceType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'deducts_balance' => true,
            'requires_approval' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function withoutApproval(): static
    {
        return $this->state(fn () => ['requires_approval' => false]);
    }

    public function notDeducting(): static
    {
        return $this->state(fn () => ['deducts_balance' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
