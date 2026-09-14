<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Validation\ValidationException;

/**
 * States reserved to the domain actions (spec 0123, D-4): a PATCH that moves
 * `task_status_id` to a status whose phase is `in_validation` or
 * `closed_positive` is refused 422, for QUALUNQUE attore — creator,
 * requester, manager or super-admin alike. Those two phases are reachable
 * ONLY through `POST /complete` and `POST /approve`; `closed_negative`
 * carries no such reservation and stays selectable by PATCH, subject to the
 * guards already in force (D-4).
 *
 * Evaluated on the RESULTING status, same convention as the sibling guards
 * (TaskClosureFeedbackGuard, TaskValidationRequirementGuard): called by
 * App\Services\TaskService::update() right after fill(), inside the write
 * transaction, only when `task_status_id` is dirty — a PATCH that leaves it
 * untouched (e.g. only `closure_feedback` on an already-closed Task, AC-013)
 * never re-judges a status that predates this rule.
 *
 * Deliberately UNCONDITIONAL on the actor: unlike
 * TaskValidationRequirementGuard (spec 0121 D-5), which exempts whoever owns
 * the mandate, this rule has no exemption — D-4 explicitly calls out
 * "anche gestore/super-admin".
 */
final class TaskActionOnlyStatusGuard
{
    private const string MESSAGE = 'This status can only be reached through the Complete action.';

    /**
     * @var array<int, TaskStatusGroup>
     */
    private const array RESERVED_GROUPS = [
        TaskStatusGroup::InValidation,
        TaskStatusGroup::ClosedPositive,
    ];

    /**
     * @throws ValidationException 422 on `task_status_id`
     */
    public function assertReachableByPatch(Task $task): void
    {
        if (! $task->isDirty('task_status_id')) {
            return;
        }

        if (! in_array($this->resolveStatus($task)?->group, self::RESERVED_GROUPS, true)) {
            return;
        }

        throw ValidationException::withMessages(['task_status_id' => [self::MESSAGE]]);
    }

    /**
     * The status the Task will HAVE once saved, mirroring
     * TaskClosureFeedbackGuard::resolveStatus(): the loaded relation is
     * reused only while it still matches `task_status_id`, since a PATCH
     * that changes the status leaves the old row loaded.
     */
    private function resolveStatus(Task $task): ?TaskStatus
    {
        $loaded = $task->relationLoaded('taskStatus') ? $task->taskStatus : null;

        if ($loaded !== null && $loaded->getKey() === $task->task_status_id) {
            return $loaded;
        }

        return TaskStatus::query()->find($task->task_status_id);
    }
}
