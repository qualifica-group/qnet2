<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderStage>
 */
class WorkOrderStageFactory extends Factory
{
    protected $model = WorkOrderStage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'closed_at' => null,
            'closed_by_id' => null,
        ];
    }

    public function forWorkOrder(WorkOrder $workOrder): static
    {
        return $this->state(fn (): array => ['work_order_id' => $workOrder->id]);
    }

    public function atPosition(int $sortOrder): static
    {
        return $this->state(fn (): array => ['sort_order' => $sortOrder]);
    }

    /**
     * D-4: a closed stage always carries who closed it and when — both
     * DELIBERATELY absent from the model's `#[Fillable]` in production, so a
     * test opts in explicitly here rather than via mass assignment.
     */
    public function closed(): static
    {
        return $this->state(fn (): array => [
            'closed_at' => now(),
            'closed_by_id' => User::factory(),
        ]);
    }
}
