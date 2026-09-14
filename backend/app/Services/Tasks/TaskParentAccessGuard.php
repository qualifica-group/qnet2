<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Who may hang a Task under a parent (spec 0125, D-3/D-4): the actor must SEE
 * the parent (TaskVisibilityScope) and hold the sub-task row of the matrix on
 * it (TaskAbilityResolver::canCreateSubtask). `exists` alone let anyone with
 * `tasks.create` attach a child to a Task they could not even open.
 *
 * An invisible parent, a missing one and a visible-but-not-writable one all
 * get the SAME field-scoped 422 on `parent_task_id`: distinguishing them would
 * let the response confirm that a Task id exists. Field-scoped for the same
 * reason as TaskHierarchyGuard (a message-only 422 is swallowed by the form).
 */
final class TaskParentAccessGuard
{
    private const string NOT_AVAILABLE_MESSAGE = 'The selected parent task is not available.';

    /**
     * @param  int|null  $parentTaskId  the parent being set; null (no parent) needs no check
     *
     * @throws ValidationException 422 on `parent_task_id`
     */
    public function assertMayAttach(?int $parentTaskId, User $actor): void
    {
        if ($parentTaskId === null) {
            return;
        }

        $parent = Task::query()->find($parentTaskId);

        if ($parent === null
            || ! TaskVisibilityScope::isVisibleTo($actor, $parent)
            || ! TaskAbilityResolver::canCreateSubtask($actor, $parent)) {
            throw ValidationException::withMessages(['parent_task_id' => [self::NOT_AVAILABLE_MESSAGE]]);
        }
    }
}
