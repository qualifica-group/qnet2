<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Tasks\TaskVisibilityScope;
use Illuminate\Support\Collection;

/**
 * The read path behind `GET /api/work-orders/{workOrder}/task-board` (spec
 * 0146, AC-011): every Task of $workOrder — root and sub-task alike, scoped
 * by `work_order_id` the SAME way `App\Services\WorkOrders\
 * WorkOrderTaskForceCloser` already treats "every Task of this commessa" —
 * narrowed by `TaskVisibilityScope` (AC-012), eager-loaded so the board never
 * N+1s (constraints): status/type/priority/requester/assignees/watchers plus
 * the two aggregates (`actual_minutes` via `withSum`, `attachments_count` via
 * `withCount`). `preventLazyLoading` (backend.md §3) stays the backstop.
 *
 * The flat list is ORDERED, not left to arbitrary row order: root Tasks
 * first, by (fase's own `sort_order`, `stage_position`) — "Senza fase" sorts
 * LAST, reading D-5's "una colonna per fase più 'Senza fase'" as the named
 * fasi followed by the one residual group — and every Task's sub-tasks
 * follow it immediately, depth-first, so a flat consumer that never re-nests
 * still sees each family contiguous.
 */
final class TaskBoardQuery
{
    /**
     * @var array<int, string>
     */
    private const array RELATIONS = ['taskStatus', 'taskType', 'taskPriority', 'taskImportance', 'requester', 'assignees', 'watchers'];

    /**
     * @return Collection<int, Task>
     */
    public function tasks(WorkOrder $workOrder, ?User $actor): Collection
    {
        $query = Task::query()
            ->where('work_order_id', $workOrder->id)
            ->with(self::RELATIONS)
            ->withSum('timeEntries as actual_minutes', 'minutes')
            ->withCount('attachments');

        $query = TaskVisibilityScope::scopeToActor($query, $actor);

        return $this->orderedFlat($query->get(), $this->stageRanks($workOrder));
    }

    /**
     * @param  iterable<int, Task>  $tasks
     * @param  array<int, int>  $stageRank
     * @return Collection<int, Task>
     */
    private function orderedFlat(iterable $tasks, array $stageRank): Collection
    {
        $childrenByParent = [];

        foreach ($tasks as $task) {
            // 0 is never a real task id: a safe sentinel key for "no parent"
            // that keeps this a single array instead of a (roots, children)
            // pair threaded through every call below.
            $childrenByParent[$task->parent_task_id ?? 0][] = $task;
        }

        $roots = $childrenByParent[0] ?? [];
        usort($roots, static fn (Task $a, Task $b): int => [
            $stageRank[$a->work_order_stage_id] ?? PHP_INT_MAX,
            $a->stage_position,
        ] <=> [
            $stageRank[$b->work_order_stage_id] ?? PHP_INT_MAX,
            $b->stage_position,
        ]);

        $ordered = [];
        $this->appendFamilies($roots, $childrenByParent, $ordered);

        return collect($ordered);
    }

    /**
     * @param  array<int, Task>  $siblings
     * @param  array<int, array<int, Task>>  $childrenByParent
     * @param  array<int, Task>  $ordered
     */
    private function appendFamilies(array $siblings, array $childrenByParent, array &$ordered): void
    {
        foreach ($siblings as $task) {
            $ordered[] = $task;
            $this->appendFamilies($childrenByParent[$task->id] ?? [], $childrenByParent, $ordered);
        }
    }

    /**
     * @return array<int, int>
     */
    private function stageRanks(WorkOrder $workOrder): array
    {
        $ranks = [];

        foreach ($workOrder->stages()->get() as $index => $stage) {
            $ranks[$stage->id] = $index;
        }

        return $ranks;
    }
}
