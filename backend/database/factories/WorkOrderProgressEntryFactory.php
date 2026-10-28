<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderProgressEntry>
 */
class WorkOrderProgressEntryFactory extends Factory
{
    protected $model = WorkOrderProgressEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'percentage' => 50,
            'recorded_on' => now()->toDateString(),
            'created_by' => null,
        ];
    }

    public function forWorkOrder(WorkOrder $workOrder): static
    {
        return $this->state(fn (): array => ['work_order_id' => $workOrder->id]);
    }

    public function reached(int $percentage, string $recordedOn): static
    {
        return $this->state(fn (): array => ['percentage' => $percentage, 'recorded_on' => $recordedOn]);
    }
}
