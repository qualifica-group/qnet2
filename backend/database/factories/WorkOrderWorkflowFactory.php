<?php

namespace Database\Factories;

use App\Models\WorkOrderWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderWorkflow>
 */
class WorkOrderWorkflowFactory extends Factory
{
    protected $model = WorkOrderWorkflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
