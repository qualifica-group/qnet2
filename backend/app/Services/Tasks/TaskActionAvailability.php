<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatusGroup;
use App\Models\Task;

/**
 * WHEN a domain action makes sense, driven by the PHASE of the Task's
 * CURRENT status (spec 0116, D-1) — never a label. `is_blocked` is read
 * only by the two methods that name it directly: the veto it casts over
 * every OTHER action is a D-8 business rule, not availability, and lives in
 * App\Services\Tasks\TaskWriteLock::assertNotBlocked(), which answers 409 for it.
 *
 * Modelled on App\Services\Contracts\ContractActionAvailability: an
 * availability rule, NOT an authorization one. Each caller
 * (App\Authorization\TasksAuthorization, App\Services\Tasks\TaskCompletionService, App\Services\Tasks\TaskActionService)
 * still ANDs it with the actor's ability (TaskAbilityResolver), and
 * TaskCompletionService/TaskActionService re-assert the same rules server-side (422).
 *
 * Injectable, unlike TaskRecordRoles/TaskAbilityResolver: only
 * TasksAuthorization, TaskCompletionService and TaskActionService consume it, never the Policy.
 */
class TaskActionAvailability
{
    /** Not already in a closing phase and not already awaiting validation. */
    public function isCompletable(Task $task): bool
    {
        return ! $this->isClosedOrInValidation($task);
    }

    /** Already sitting in a closing phase or awaiting validation. */
    public function isUncompletable(Task $task): bool
    {
        return $this->isClosedOrInValidation($task);
    }

    public function isValidatable(Task $task): bool
    {
        return $this->group($task) === TaskStatusGroup::InValidation;
    }

    public function isBlockable(Task $task): bool
    {
        return $task->is_blocked === false;
    }

    public function isUnblockable(Task $task): bool
    {
        return $task->is_blocked === true;
    }

    private function isClosedOrInValidation(Task $task): bool
    {
        return in_array($this->group($task), [
            TaskStatusGroup::InValidation,
            TaskStatusGroup::ClosedPositive,
            TaskStatusGroup::ClosedNegative,
        ], true);
    }

    private function group(Task $task): ?TaskStatusGroup
    {
        $task->loadMissing('taskStatus');

        return $task->taskStatus?->group;
    }
}
