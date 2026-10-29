<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use App\Models\WorkOrderWorkflowStatus;

if (! function_exists('globalProgressStatus')) {
    /**
     * A custom status of the GLOBAL default set (the set a line-less work
     * order resolves to) carrying the given progress percentage.
     */
    function globalProgressStatus(?int $percentage, string $name = 'Working'): WorkOrderWorkflowStatus
    {
        return WorkOrderWorkflowStatus::factory()->global()->create([
            'name' => $name.' '.uniqid(),
            'progress_percentage' => $percentage,
        ]);
    }
}

if (! function_exists('seedProgressEntry')) {
    function seedProgressEntry(WorkOrder $workOrder, int $increment, ?User $by = null): WorkOrderProgressEntry
    {
        return $workOrder->progressEntries()->create([
            'percentage' => $increment,
            'recorded_on' => now()->subDay()->toDateString(),
            'created_by' => $by?->id ?? User::factory()->create()->id,
        ]);
    }
}

if (! function_exists('linkedTask')) {
    /**
     * A task of $workOrder carrying $status as its snapshot (the column is
     * outside #[Fillable], so it is forced like the generator does).
     *
     * @param  array<string, mixed>  $attributes
     */
    function linkedTask(WorkOrder $workOrder, ?WorkOrderWorkflowStatus $status, User $assignee, array $attributes = []): Task
    {
        $task = Task::factory()->create(['work_order_id' => $workOrder->id, ...$attributes]);
        $task->forceFill(['work_order_workflow_status_id' => $status?->id])->save();
        $task->assignees()->attach($assignee->id);

        return $task->fresh();
    }
}

if (! function_exists('progressTotal')) {
    function progressTotal(WorkOrder $workOrder): int
    {
        return (int) $workOrder->progressEntries()->sum('percentage');
    }
}
