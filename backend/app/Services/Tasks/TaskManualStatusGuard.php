<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Validation\ValidationException;

/**
 * Whether a PATCH may move `task_status_id` AT ALL, decided on the Task's
 * CURRENT (pre-fill) persisted state (spec 0126, D-4b/D-4c) — never on the
 * resulting one, which stays `TaskActionOnlyStatusGuard`'s own job (D-4a,
 * the TARGET group). Two independent vetoes, evaluated ONLY when
 * `task_status_id` is actually submitted and differs from what is currently
 * persisted (a PATCH that repeats the current value, or omits the key,
 * changes nothing and is never judged):
 *  - (b) the Task's CURRENT phase must be `open` or `pending` — from
 *    `in_validation` the domain actions (approve/reject) are the only door
 *    out, from a closed phase "set not completed" (uncomplete) is;
 *  - (c) a BLOCKED Task admits no manual status change at all, regardless of
 *    its phase — distinct from `TaskWriteLock`, which already lets
 *    `task_status_id` ride through a frozen Task as an OPERATIVE key; this
 *    guard adds the D-6 veto that key alone still needs.
 *
 * Checked in THIS order, (b) then (c): once (b) has passed, the current
 * phase is known to be open/pending, so (a)'s target-group check
 * (`TaskActionOnlyStatusGuard`, run AFTER fill()) is only ever reached from
 * that same starting phase — the one combination spec 0123 already exercised
 * unconditionally. A Task frozen by its PHASE reports the (b) message even
 * when it also happens to be blocked, since (b) fires first; the ordinary
 * case a blocked Task sitting in `open`/`pending` (AC-010) reports the
 * blocked message, because (b) passes and (c) is what stops it.
 *
 * `App\Services\TaskService::update()` calls `assertAllowed()` right after
 * `TaskWriteLock::assertStructuralWriteAllowed()` and BEFORE `fill()`, so
 * both read the Task exactly as it stood when the request arrived.
 * `App\Authorization\TasksAuthorization::actionPermissions()` reads
 * `isCurrentPhaseOpenOrPending()` for the `change_status` flag, so the two
 * never drift (constraints: the matrix rule stated once).
 *
 * Static and stateless, the same zero-argument-constructible convention as
 * `TaskWriteLock`/`TaskAbilityResolver`.
 */
final class TaskManualStatusGuard
{
    private const string ACTION_ONLY_MESSAGE = 'The status of this task can only be changed through its actions.';

    private const string BLOCKED_MESSAGE = 'A blocked task cannot change status.';

    /**
     * PUBLIC (spec 0154, D-10): App\Services\Tasks\TaskInitialStatusResolver
     * reuses this exact set as the "manually selectable at creation" allow
     * list, so the phases a PATCH may move `task_status_id` INTO and the
     * ones a POST may CHOOSE it FROM can never drift apart.
     *
     * @var array<int, TaskStatusGroup>
     */
    public const array MANUAL_GROUPS = [TaskStatusGroup::Open, TaskStatusGroup::Pending];

    /**
     * @throws ValidationException 422 on `task_status_id`
     */
    public static function assertAllowed(Task $task, ?int $submittedStatusId): void
    {
        if ($submittedStatusId === null || $submittedStatusId === $task->task_status_id) {
            return;
        }

        if (! self::isCurrentPhaseOpenOrPending($task)) {
            throw ValidationException::withMessages(['task_status_id' => [self::ACTION_ONLY_MESSAGE]]);
        }

        if ($task->is_blocked) {
            throw ValidationException::withMessages(['task_status_id' => [self::BLOCKED_MESSAGE]]);
        }
    }

    /**
     * WHETHER the Task's CURRENT phase admits a manual status change at all
     * (D-4b), regardless of `is_blocked` — the `change_status` flag ANDs
     * this with `! is_blocked` itself, since the flag carries no "which
     * message" concern the assertion above does.
     */
    public static function isCurrentPhaseOpenOrPending(Task $task): bool
    {
        return in_array(self::group($task), self::MANUAL_GROUPS, true);
    }

    private static function group(Task $task): ?TaskStatusGroup
    {
        $loaded = $task->relationLoaded('taskStatus') ? $task->taskStatus : null;

        $status = $loaded !== null && $loaded->getKey() === $task->task_status_id
            ? $loaded
            : TaskStatus::query()->find($task->task_status_id);

        return $status?->group;
    }
}
