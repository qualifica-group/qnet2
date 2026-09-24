<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
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
 *
 * The cascade (spec 0123, D-9, decision utente) extends the SAME lock up the
 * `parent_task_id` chain: a Task whose parent or any ancestor isLocked() is
 * structurally frozen exactly like a directly frozen one — same 422/409,
 * same messages ("messaggi esistenti") — while its own operative actions
 * (status, note, segnatempo, complete/uncomplete...) stay untouched, because
 * isLockedByAncestor() is read ONLY by assertStructuralWriteAllowed() and
 * assertDeletable(), never by assertNotBlocked() or the completion/action
 * services. assertParentChainUnlocked() is the mirror rule for the EDGE
 * itself: a Task may not be created, nor moved by PATCH, under a
 * parent_task_id whose chain is locked — a distinct 422, on `parent_task_id`,
 * from either of the two above. Both walks defend against a pre-existing
 * cycle the same way TaskHierarchyGuard does (stop revisiting an id already
 * seen) even though this class is not the one that enforces acyclicity.
 */
final class TaskWriteLock
{
    /**
     * @var array<int, string>
     */
    public const array OPERATIVE_KEYS = ['task_status_id', 'closure_feedback'];

    private const string STRUCTURAL_WRITE_MESSAGE = 'This task is frozen: only its status and closure feedback can be changed.';

    private const string DELETE_MESSAGE = 'This task is frozen and cannot be deleted.';

    private const string BLOCKED_ACTION_MESSAGE = 'This task is blocked: unblock it before performing this action.';

    private const string FROZEN_PARENT_MESSAGE = 'The parent task is frozen: sub-tasks cannot be added to it.';

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
     * Spec 0123, D-9: true when $task's parent, or any ancestor above it, is
     * itself isLocked() — the cascade climbs `parent_task_id` one row at a
     * time, starting AT the parent (so "the parent itself frozen" and "an
     * ancestor further up frozen" are the SAME check). $task need not be
     * persisted: create() calls this on an in-memory row that only carries
     * `parent_task_id`, before the insert.
     */
    public static function isLockedByAncestor(Task $task): bool
    {
        $visited = [];
        $ancestorId = $task->parent_task_id;

        while ($ancestorId !== null) {
            // Defence in depth against a pre-existing cycle among ancestors
            // (unreachable while TaskHierarchyGuard is the only write path):
            // stop instead of looping forever.
            if (isset($visited[$ancestorId])) {
                return false;
            }

            $ancestor = Task::query()->find($ancestorId);

            if ($ancestor === null) {
                return false;
            }

            if (self::isLocked($ancestor)) {
                return true;
            }

            $visited[$ancestorId] = true;
            $ancestorId = $ancestor->parent_task_id;
        }

        return false;
    }

    /**
     * @param  array<int, string>  $submittedKeys
     * @param  User|null  $actor  D-8 (spec 0153): a super-admin bypasses
     *                            $task's OWN frozen-GROUP veto (closed/in-validation) for this PATCH —
     *                            `is_blocked` is not, and the ancestor cascade is not: both stay
     *                            universal, whatever the actor's role.
     *
     * @throws ValidationException 422, one message per structural key
     */
    public static function assertStructuralWriteAllowed(Task $task, array $submittedKeys, ?User $actor = null): void
    {
        if (! self::isLockedForUpdate($task, $actor) && ! self::isLockedByAncestor($task)) {
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

    /**
     * Spec 0116 D-8: a blocked Task admits no domain action except unblock().
     * Shared by TaskCompletionService and TaskActionService.
     */
    public static function assertNotBlocked(Task $task): void
    {
        if ($task->is_blocked) {
            abort(409, self::BLOCKED_ACTION_MESSAGE);
        }
    }

    public static function assertDeletable(Task $task): void
    {
        if (self::isLocked($task) || self::isLockedByAncestor($task)) {
            abort(409, self::DELETE_MESSAGE);
        }
    }

    /**
     * Spec 0123, D-9: the edge itself — a Task may not be created, nor moved
     * by PATCH, under a `parent_task_id` whose chain (the candidate parent
     * itself, or any ancestor above it) isLocked(). Distinct from
     * assertStructuralWriteAllowed()/assertDeletable(): those protect a Task
     * ALREADY under a frozen chain, this one refuses the write that would
     * PUT it there. `App\Services\TaskService` calls it with the RESULTING
     * `parent_task_id` already on $task — on create unconditionally, on
     * update only when the key was actually submitted.
     *
     * @throws ValidationException 422 on `parent_task_id`
     */
    public static function assertParentChainUnlocked(Task $task): void
    {
        if (! self::isLockedByAncestor($task)) {
            return;
        }

        throw ValidationException::withMessages(['parent_task_id' => [self::FROZEN_PARENT_MESSAGE]]);
    }

    private static function group(Task $task): ?TaskStatusGroup
    {
        $task->loadMissing('taskStatus');

        return $task->taskStatus?->group;
    }

    /**
     * D-8 (spec 0153): the UPDATE-only twin of isLocked() — a privileged
     * actor still freezes on `is_blocked`, but never on the frozen GROUP
     * alone (closed/in-validation). Every other actor sees the full
     * isLocked() rule, unchanged.
     */
    private static function isLockedForUpdate(Task $task, ?User $actor): bool
    {
        if ($actor !== null && $actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE)) {
            return $task->is_blocked;
        }

        return self::isLocked($task);
    }
}
