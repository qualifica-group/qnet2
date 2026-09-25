<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

/**
 * D-1/D-2/D-4/D-5 (spec 0167): once $task's own fase or tree position has
 * moved, every segnatempo voce tied to a task in $task's own SUBTREE is
 * realigned in ONE query-builder UPDATE onto the subtree's effective fase
 * (`TaskEffectiveStage`, D-1) — never row by row, and never through Eloquent
 * (D-4: this is a system-derived effect of the task's own write, not a
 * fresh user action worth its own activity-log entry per voce — `TimeEntry`
 * uses `LogsModelActivity`, which only listens to Eloquent save events a
 * query-builder `update()` never fires).
 *
 * TWO entry points, one per caller's own write shape (D-3, no backfill,
 * AC-008, is never optional): `realignIfMoved()` for
 * `App\Services\TaskService::update()`, called AFTER `$task->save()`, gated
 * on `wasChanged()` — the ONLY check an already-saved Eloquent instance can
 * answer correctly. `realign()` (unconditional) for
 * `App\Services\WorkOrders\TaskStagePositioner::move()`, which never calls
 * `$task->save()` at all (its own writes go through the query builder, by
 * design, so `wasChanged()` would read empty there regardless of what
 * changed) — its OWN cross-fase branch is already the gate (a same-fase
 * reorder never reaches this call in the first place).
 *
 * The subtree walk is seeded at $task itself, not its root (AC-006): moving
 * a sub-task under a different parent only realigns the piece of the tree
 * that actually moved — the rest of the OLD tree, untouched, keeps its own
 * voci exactly as they are (D-3). $task being the root itself (the ordinary
 * fase-change case, AC-001/AC-004) degenerates to "the whole tree", the
 * same rule.
 *
 * Voci with no `task_id` (D-5, standalone fase) are structurally excluded:
 * the `whereIn('task_id', ...)` below can only ever match a voce that is
 * `task_id`-linked in the first place.
 */
final class TaskTimeEntryStageRealigner
{
    private const array EFFECTIVE_STAGE_DEPENDENCIES = ['work_order_stage_id', 'parent_task_id'];

    /**
     * `App\Services\TaskService::update()`'s own entry point (AC-008): only
     * realigns when $task's last `save()` actually touched one of the two
     * columns the effective fase depends on — an untouched PATCH never
     * re-derives an already-aligned (or deliberately pregresso-disallineata)
     * voce.
     */
    public function realignIfMoved(Task $task): void
    {
        if ($task->wasChanged(self::EFFECTIVE_STAGE_DEPENDENCIES)) {
            $this->realign($task);
        }
    }

    public function realign(Task $task): void
    {
        $stageId = TaskEffectiveStage::forTask($task);
        $taskIds = $this->subtreeIds($task);

        TimeEntry::query()->whereIn('task_id', $taskIds)->update(['work_order_stage_id' => $stageId]);
    }

    /**
     * Breadth-first, level by level (mirrors
     * `WorkOrderStageService::stageTaskIds()`): one query per depth, never
     * per row, over a hierarchy `TaskHierarchyGuard` keeps acyclic.
     *
     * @return Collection<int, int>
     */
    private function subtreeIds(Task $task): Collection
    {
        $ids = Collection::make([$task->id]);
        $frontier = $ids;

        while ($frontier->isNotEmpty()) {
            $children = Task::query()->whereIn('parent_task_id', $frontier)->pluck('id');
            $ids = $ids->merge($children);
            $frontier = $children;
        }

        return $ids;
    }
}
