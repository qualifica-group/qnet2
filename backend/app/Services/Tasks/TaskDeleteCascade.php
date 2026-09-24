<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The cascade side of Task deletion (D-5, spec 0153, REQUIREMENT CHANGED):
 * deleting a Task deletes its WHOLE sub-tree, or nothing at all, rather than
 * the old 409-for-sub-tasks refusal. `TaskService::delete()` calls
 * assertDeletable() for every collected descendant, inside its OWN
 * transaction, before deleting anything — this class only decides WHICH rows
 * belong to the sub-tree and WHETHER each one may be deleted; the actual
 * writes stay in TaskService.
 *
 * Static and stateless like the module's other guards
 * (TaskWriteLock/TaskAbilityResolver): no dependency of its own, called only
 * from TaskService.
 */
final class TaskDeleteCascade
{
    /**
     * Every direct and indirect child of $task, level order: a Task always
     * appears before its own children, so reversing the result deletes
     * leaves before their ancestors (`parent_task_id` is restrictOnDelete).
     * Ignores TaskVisibilityScope on purpose — the old sub-task guard
     * already applied the same "the constraint is about the data, not about
     * who is looking" rule (AC-017 of spec 0101).
     *
     * @return array<int, Task>
     */
    public static function collectDescendants(Task $task): array
    {
        $descendants = [];
        $queue = [$task];

        while ($queue !== []) {
            $current = array_shift($queue);
            $children = Task::query()->where('parent_task_id', $current->id)->get();

            foreach ($children as $child) {
                $descendants[] = $child;
                $queue[] = $child;
            }
        }

        return $descendants;
    }

    /**
     * A descendant $actor may not delete (role OR state, the SAME
     * `TaskAbilityResolver::canDelete()` the root itself was checked
     * against) aborts the WHOLE cascade — a 422 naming the offending id,
     * exactly the shape `TableBulkDeleteService`'s per-row `guarded` reason
     * relies on (it catches ValidationException too).
     */
    public static function assertDescendantDeletable(Task $descendant, User $actor): void
    {
        if (! TaskAbilityResolver::canDelete($actor, $descendant)) {
            throw ValidationException::withMessages([
                'task' => ["Sub-task #{$descendant->id} cannot be deleted."],
            ]);
        }
    }
}
