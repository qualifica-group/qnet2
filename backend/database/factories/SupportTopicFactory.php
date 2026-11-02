<?php

namespace Database\Factories;

use App\Models\SupportTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTopic>
 */
class SupportTopicFactory extends Factory
{
    protected $model = SupportTopic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'icon' => null,
            'sort_order' => 0,
            'is_active' => true,
            'due_days' => 2,
            'is_private' => false,
            'requires_closure_feedback' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
