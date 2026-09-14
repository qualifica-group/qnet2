<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Closes the PATCH bypass of the completion percorso (spec 0121, D-5): a
 * Task flagged `requires_validation` may not be walked straight into a
 * closing status by PATCH `task_status_id` when the actor does not own the
 * mandate — that is exactly the walk `POST /complete` would have routed into
 * validation instead. `requires_validation` is itself a PROTECTED field
 * (D-1), so an actor without the mandate cannot flip it off in the SAME
 * PATCH to dodge this guard; whoever owns the mandate is exempt, matching
 * `TaskAbilityResolver::completionRequiresValidation()` (D-2) — the single
 * source of the percorso rule, reused here rather than re-derived.
 *
 * Evaluated on the RESULTING state, same convention as
 * `TaskClosureFeedbackGuard`: `App\Services\TaskService::update()` calls
 * `assertClosableBy()` after `fill()` and before `save()`, inside the write
 * transaction, so a refusal leaves the Task untouched.
 */
final class TaskValidationRequirementGuard
{
    /**
     * @throws ValidationException 422 on `task_status_id`
     */
    public function assertClosableBy(Task $task, User $actor): void
    {
        if (! $task->isDirty('task_status_id')) {
            return;
        }

        if (! $this->resolveStatus($task)?->isClosing()) {
            return;
        }

        if (! TaskAbilityResolver::completionRequiresValidation($actor, $task)) {
            return;
        }

        throw ValidationException::withMessages([
            'task_status_id' => ['This task requires validation: complete it instead of closing it directly.'],
        ]);
    }

    /**
     * The status the Task will HAVE once saved, mirroring
     * `TaskClosureFeedbackGuard::resolveStatus()`: the loaded relation is
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
