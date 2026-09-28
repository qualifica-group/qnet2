<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\Task;
use App\Models\TaskTemplateItem;

/**
 * Pure positioning rules `WorkOrderTaskGenerator` applies to each Task it
 * materializes off a `TaskTemplateItem` row, extracted so that class stays
 * under engineering.md's file-size guidance.
 *
 * `rootStage()` (spec 0146, D-2): a ROOT row's `work_order_stage_id` and its
 * `stage_position`, progressive WITHIN that stage (or "Senza fase") in the
 * order the template's items are read — the caller only invokes this for a
 * root row (spec 0172, AC-010: a sub-item's presence must never shift a root
 * sibling's own count).
 *
 * `nextSubtaskPosition()` (spec 0172, D-1): 0..n among the rows sharing the
 * same DIRECT parent Task, in item order — the same shape
 * `App\Services\Tasks\TaskSubtaskBatchCreator` assigns for a manually
 * created sub-task.
 */
final class GeneratedTaskPositioner
{
    /**
     * @param  array<int, int>  $stageIdsByTemplateStageId
     * @param  array<int, int>  $stagePositions
     * @return array{0: ?int, 1: int}
     */
    public function rootStage(TaskTemplateItem $item, array $stageIdsByTemplateStageId, array &$stagePositions): array
    {
        $workOrderStageId = $item->task_template_stage_id === null
            ? null
            : $stageIdsByTemplateStageId[$item->task_template_stage_id];
        // "Senza fase" (null) is its own group too — 0 is a safe sentinel
        // key: real WorkOrderStage ids start at 1.
        $positionGroup = $workOrderStageId ?? 0;
        $stagePosition = $stagePositions[$positionGroup] ?? 0;
        $stagePositions[$positionGroup] = $stagePosition + 1;

        return [$workOrderStageId, $stagePosition];
    }

    /**
     * @param  array<int, int>  $subtaskPositions  keyed by the parent Task's own id
     */
    public function nextSubtaskPosition(Task $parentTask, array &$subtaskPositions): int
    {
        $position = $subtaskPositions[$parentTask->id] ?? 0;
        $subtaskPositions[$parentTask->id] = $position + 1;

        return $position;
    }
}
