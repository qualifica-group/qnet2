<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;

/**
 * The D-1 "fase effettiva" rule (spec 0167): the `work_order_stage_id` a
 * Task's own segnatempo voci are tied to is never read off the Task itself
 * when it is a sub-task (`TaskStageGuard` keeps a sub-task's own column
 * null) — it is the ROOT of its tree's, walking `parent_task_id` up to the
 * top; null when the root itself carries no fase. The ONE implementation
 * `TimeEntryLinkResolver::fromTask()` (write-time) and
 * `TaskTimeEntryStageRealigner` (D-2 realignment) both read, so the rule can
 * never drift between the two.
 *
 * The walk is bounded by the tree's own depth (`TaskHierarchyGuard` keeps it
 * acyclic) — never more than one query per ancestor level, the same
 * level-by-level posture as `TaskSubtaskTreeLoader`.
 */
final class TaskEffectiveStage
{
    public static function forTask(Task $task): ?int
    {
        $current = $task;

        while ($current->parent_task_id !== null) {
            $parent = Task::query()->find($current->parent_task_id);

            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return $current->work_order_stage_id;
    }
}
