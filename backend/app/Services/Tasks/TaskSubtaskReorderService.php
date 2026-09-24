<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Reorders a Task's DIRECT sub-tasks (spec 0155, D-4/D-5). `ids` must name
 * EXACTLY the children the actor can SEE (TaskVisibilityScope) — the only ids
 * the detail panel knows — with no foreign or missing id, the set-equality
 * shape App\Services\WorkOrders\WorkOrderStageService::reorder() already
 * uses. A child the actor cannot see keeps its own slot in the sequence and
 * never appears in the answer, so the endpoint neither blocks a partial viewer
 * nor leaks what they may not see. The authority over the write is the
 * parent's `update` ability, checked in the controller.
 */
final class TaskSubtaskReorderService
{
    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Task>
     */
    public function reorder(Task $parent, array $ids, User $actor): Collection
    {
        // Step 1: the full current order, and the part of it the actor sees.
        $allIds = $parent->subtasks()->pluck('id')->all();
        $visibleIds = $this->visibleSubtasks($parent, $actor)->pluck('id')->all();

        // Step 2: `ids` must be a permutation of exactly the visible children.
        $this->assertValidPermutation($ids, $visibleIds);

        // Step 3: refill the visible slots in the requested order; invisible
        // children stay where they are, then renumber the whole sequence.
        $queue = $ids;
        $sequence = [];

        foreach ($allIds as $id) {
            $sequence[] = in_array($id, $visibleIds, true) ? (int) array_shift($queue) : $id;
        }

        DB::transaction(function () use ($sequence): void {
            foreach ($sequence as $position => $id) {
                Task::query()->whereKey($id)->update(['subtask_position' => $position]);
            }
        });

        // Step 4: answer with the visible children only, preloaded for the resource.
        return TaskActionAvailability::withOpenSubtasksCount(
            $this->visibleSubtasks($parent, $actor)->getQuery()->with(['taskStatus', 'assignees', 'completionSubtasks.taskStatus']),
        )->get();
    }

    /**
     * @return HasMany<Task, Task>
     */
    private function visibleSubtasks(Task $parent, User $actor): HasMany
    {
        $relation = $parent->subtasks();
        TaskVisibilityScope::scopeToActor($relation->getQuery(), $actor);

        return $relation;
    }

    /**
     * @param  array<int, int>  $ids
     * @param  array<int, int>  $visibleIds
     */
    private function assertValidPermutation(array $ids, array $visibleIds): void
    {
        if (count($ids) !== count(array_unique($ids))) {
            abort(422, 'ids contains duplicate ids.');
        }

        if (array_diff($ids, $visibleIds) !== [] || array_diff($visibleIds, $ids) !== []) {
            abort(422, 'ids must contain exactly the visible direct sub-tasks of this task (no missing, no foreign id).');
        }
    }
}
