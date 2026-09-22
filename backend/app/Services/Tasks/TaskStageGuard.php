<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use App\Services\WorkOrders\TaskStagePositioner;

/**
 * The `work_order_stage_id` rule on a Task's own create/update (spec 0146,
 * D-3/AC-015/AC-016), the RESULTING-state counterpart to the FormRequests'
 * shape-only `exists:work_order_stages,id` — the same split every other
 * TaskService guard already draws (e.g. `TaskReferentRegistryGuard`).
 *
 * `applyOnCreate()`/`applyOnUpdate()` mirror `TaskDescriptionWriter`'s own
 * two-method shape: a brand-new Task has no PRIOR stage to silently detach
 * (AC-016 is inherently an update-only concern), so the create path is the
 * simpler of the two. Both mutate $task in memory only, inside the caller's
 * own write transaction (`App\Services\TaskService`), and both delegate the
 * actual placement to `TaskStagePositioner::appendToStage()` — the SAME
 * accoding rule the task board's own "+ Task" and `move()` use.
 */
final class TaskStageGuard
{
    public function __construct(private readonly TaskStagePositioner $positioner) {}

    /**
     * D-3: a sub-task never carries a stage — an explicit non-null value is
     * 422, never silently ignored (there is no prior state to fall back to
     * on a create).
     */
    public function applyOnCreate(Task $task): void
    {
        if ($task->parent_task_id !== null) {
            abort_if($task->work_order_stage_id !== null, 422, 'work_order_stage_id is prohibited on a sub-task.');

            return;
        }

        $this->assignIfPresent($task, stageIdSubmitted: $task->work_order_stage_id !== null);
    }

    /**
     * $stageIdSubmitted distinguishes an explicit client choice (422/409 on
     * an invalid one) from a value merely INHERITED from before this PATCH —
     * AC-016 detaches that one silently instead, the same "resulting state,
     * submitted-keys-aware" shape `App\Services\TaskService::update()`
     * already uses for its other guards.
     */
    public function applyOnUpdate(Task $task, bool $stageIdSubmitted): void
    {
        if ($task->parent_task_id !== null) {
            abort_if($stageIdSubmitted && $task->work_order_stage_id !== null, 422, 'work_order_stage_id is prohibited on a sub-task.');

            $task->work_order_stage_id = null;
            $task->stage_position = 0;

            return;
        }

        $this->assignIfPresent($task, $stageIdSubmitted);
    }

    private function assignIfPresent(Task $task, bool $stageIdSubmitted): void
    {
        if ($task->work_order_stage_id === null) {
            return;
        }

        $stage = WorkOrderStage::find($task->work_order_stage_id);
        $belongsToWorkOrder = $stage !== null && $stage->work_order_id === $task->work_order_id;

        if (! $belongsToWorkOrder) {
            abort_if($stageIdSubmitted, 422, 'work_order_stage_id must belong to the same work order.');

            // AC-016: not a fresh mistake — $task's own work_order_id moved
            // (or emptied) elsewhere in this same write, and the INHERITED
            // stage no longer applies. Detach silently rather than error.
            $task->work_order_stage_id = null;
            $task->stage_position = 0;

            return;
        }

        abort_if($stageIdSubmitted && $stage->isClosed(), 409, 'This stage is closed.');

        // AC-015: a stage assignment that actually CHANGES is always
        // accoded at the end — resubmitting the SAME stage (not dirty) never
        // re-splices a Task past siblings it already sits among.
        if ($task->isDirty('work_order_stage_id')) {
            $this->positioner->appendToStage(WorkOrder::findOrFail((int) $task->work_order_id), $stage, $task);
        }
    }
}
