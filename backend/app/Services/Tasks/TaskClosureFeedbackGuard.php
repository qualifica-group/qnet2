<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Validation\ValidationException;

/**
 * Closing feedback rule (spec 0101, D-7): a Task flagged
 * `requires_closure_feedback` may not reach a CLOSING status without a
 * non-empty `closure_feedback`.
 *
 * The rule is evaluated on the RESULTING state, never on the submitted
 * payload: TaskService calls it after `fill()` and BEFORE `save()`, inside
 * the write transaction, so a partial PATCH that submits only
 * `task_status_id` still reads the flag and the feedback off the persisted
 * record (AC-035) and a violation leaves the Task untouched (AC-030). This
 * cannot live in a FormRequest, which knows nothing of the persisted row.
 *
 * "Closing" is decided by `system_key` alone (TaskStatus::isClosing(),
 * App\Enums\TaskStatusSystemKey), never by a label (AC-023/AC-024) — so a
 * CUSTOM status (`system_key` NULL) is never closing and never triggers the
 * rule, the consequence D-5 declares and AC-034 pins.
 */
final class TaskClosureFeedbackGuard
{
    /**
     * @throws ValidationException 422 on `closure_feedback`
     */
    public function assertSatisfied(Task $task): void
    {
        if (! $task->requires_closure_feedback) {
            return;
        }

        if (! $this->resolveStatus($task)?->isClosing()) {
            return;
        }

        if (trim((string) $task->closure_feedback) !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'closure_feedback' => ['A closure feedback is required to move this task to a closing status.'],
        ]);
    }

    /**
     * The status the Task will HAVE once saved. The loaded relation is reused
     * only while it still matches `task_status_id`: a PATCH that changes the
     * status leaves the old row loaded, and trusting it would evaluate the
     * rule against the status the Task is leaving.
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
