<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use Illuminate\Validation\ValidationException;

/**
 * Sub-task hierarchy rule (spec 0101, D-12): a Task may be neither its own
 * parent nor part of a cycle. Depth is deliberately NOT limited — no limit
 * was requested and inventing one would be a business rule.
 *
 * Invoked by TaskService inside the write transaction, on the RESULTING
 * `parent_task_id` (a partial PATCH may submit only that key), so a refused
 * hierarchy leaves the Task untouched (AC-013).
 *
 * The refusal is a 422 as D-12 requires, raised FIELD-SCOPED on
 * `parent_task_id` rather than as a bare message-only HTTP refusal. The status
 * code is the same either way; what differs is whether the user ever sees it. The
 * shared client helper `frontend/src/features/auth/form-errors.ts` reports
 * "handled" for ANY 422, including one carrying no `errors` block, and its
 * callers only raise a generic banner when it reports "not handled" — so a
 * message-only 422 is swallowed and Save appears to do nothing. Same shape as
 * the sibling TaskClosureFeedbackGuard, and the error lands on the field the
 * operator has to change.
 *
 * The walk climbs the ancestor chain one row at a time reading only the FK
 * column: the chain is short by construction and the alternative (loading
 * whole ancestor models) would buy nothing.
 */
final class TaskHierarchyGuard
{
    /**
     * @param  int|null  $taskId  the Task being written — null on create, where
     *                            no cycle can exist yet because the row has no id
     * @param  int|null  $parentTaskId  the RESULTING parent
     *
     * @throws ValidationException 422 on `parent_task_id`
     */
    public function assertAcyclic(?int $taskId, ?int $parentTaskId): void
    {
        if ($parentTaskId === null || $taskId === null) {
            return;
        }

        if ($taskId === $parentTaskId) {
            $this->refuse('A task cannot be its own parent.');
        }

        $visited = [];
        $ancestorId = $parentTaskId;

        while ($ancestorId !== null) {
            if ($ancestorId === $taskId) {
                $this->refuse('This parent would create a cycle in the sub-task chain.');
            }

            // Defence in depth against a pre-existing cycle among ancestors
            // (unreachable while this guard is the only write path): stop
            // instead of looping forever.
            if (isset($visited[$ancestorId])) {
                return;
            }

            $visited[$ancestorId] = true;
            $ancestorId = $this->parentIdOf($ancestorId);
        }
    }

    /**
     * @throws ValidationException
     */
    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['parent_task_id' => [$message]]);
    }

    private function parentIdOf(int $taskId): ?int
    {
        $parentId = Task::query()->whereKey($taskId)->value('parent_task_id');

        return $parentId === null ? null : (int) $parentId;
    }
}
