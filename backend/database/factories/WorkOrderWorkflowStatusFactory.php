<?php

namespace Database\Factories;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderWorkflowStatus>
 */
class WorkOrderWorkflowStatusFactory extends Factory
{
    protected $model = WorkOrderWorkflowStatus::class;

    /** Incrementing counter backing `sort_order`. */
    private static int $nextSortOrder = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_workflow_id' => WorkOrderWorkflow::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'color' => fake()->randomElement(['slate', 'green', 'red', 'blue']),
            'sort_order' => self::$nextSortOrder++,
            'system_key' => null,
            'group' => WorkOrderStatus::InProgress,
            'progress_percentage' => null,
        ];
    }

    /**
     * Marks the row as one of the three mandatory system rows ('open'/
     * 'completed'/'closed', spec 0322 D-2).
     */
    public function system(string $key): static
    {
        return $this->state(fn () => match ($key) {
            'completed' => ['system_key' => 'completed', 'name' => 'Completata', 'sort_order' => 998, 'group' => WorkOrderStatus::Completed],
            'closed' => ['system_key' => 'closed', 'name' => 'Chiusa', 'sort_order' => 999, 'group' => WorkOrderStatus::Closed],
            default => ['system_key' => 'open', 'name' => 'Aperta', 'sort_order' => 0, 'group' => WorkOrderStatus::Open],
        });
    }

    /**
     * Places the row in the GLOBAL default set (work_order_workflow_id null).
     */
    public function global(): static
    {
        return $this->state(fn () => ['work_order_workflow_id' => null]);
    }
}
