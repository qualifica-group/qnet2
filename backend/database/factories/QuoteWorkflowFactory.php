<?php

namespace Database\Factories;

use App\Models\QuoteWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteWorkflow>
 */
class QuoteWorkflowFactory extends Factory
{
    protected $model = QuoteWorkflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'is_active' => true,
            'criteria_signature' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
