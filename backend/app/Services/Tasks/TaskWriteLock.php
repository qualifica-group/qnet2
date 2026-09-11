<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

/**
 * Structural write lock over a FROZEN Task (spec 0116, D-7): frozen when
 * `is_blocked` is true OR the phase of its current status is one of
 * `in_validation`, `closed_positive` or `closed_negative` — a negatively
 * closed Task is as closed as a positively closed one.
 *
 * On a frozen Task the PATCH still accepts the two OPERATIVE keys
 * (`task_status_id`, `closure_feedback`); every other submitted key is
 * refused 422, one message per key, while the rest of the payload is still
 * applied. `App\Services\TaskService` calls
 * assertStructuralWriteAllowed() with the keys actually SUBMITTED (not the
 * resulting ones), inside the write transaction, before save() — a partial
 * PATCH touching nothing structural always passes.
 *
 * DELETE gets no operative exception: assertDeletable() refuses outright,
 * with a hardcoded English message, mirroring the convention
 * TaskService::delete()'s own sub-task guard already set
 * (`abort(409, 'This task has sub-tasks and cannot be deleted.')`).
 *
 * Static and stateless like TaskRecordRoles/TaskAbilityResolver: this class
 * is invoked from TaskService, not from the Policy, but there is no reason
 * to diverge from the module's zero-argument-constructible convention.
 */
final class TaskWriteLock
{
    /**
     * @var array<int, string>
     */
    public const array OPERATIVE_KEYS = ['task_status_id', 'closure_feedback'];

    private const string STRUCTURAL_WRITE_MESSAGE = 'This task is frozen: only its status and closure feedback can be changed.';

    private const string DELETE_MESSAGE = 'This task is frozen and cannot be deleted.';

    /**
     * @var array<int, TaskStatusGroup>
     */
    private const array FROZEN_GROUPS = [
        TaskStatusGroup::InValidation,
        TaskStatusGroup::ClosedPositive,
        TaskStatusGroup::ClosedNegative,
    ];

    public static function isLocked(Task $task): bool
    {
        return $task->is_blocked || in_array(self::group($task), self::FROZEN_GROUPS, true);
    }

    /**
     * @param  array<int, string>  $submittedKeys
     *
     * @throws ValidationException 422, one message per structural key
     */
    public static function assertStructuralWriteAllowed(Task $task, array $submittedKeys): void
    {
        if (! self::isLocked($task)) {
            return;
        }

        $structuralKeys = array_diff($submittedKeys, self::OPERATIVE_KEYS);

        if ($structuralKeys === []) {
            return;
        }

        throw ValidationException::withMessages(
            array_fill_keys($structuralKeys, [self::STRUCTURAL_WRITE_MESSAGE]),
        );
    }

    public static function assertDeletable(Task $task): void
    {
        if (self::isLocked($task)) {
            abort(409, self::DELETE_MESSAGE);
        }
    }

    private static function group(Task $task): ?TaskStatusGroup
    {
        $task->loadMissing('taskStatus');

        return $task->taskStatus?->group;
    }
}
