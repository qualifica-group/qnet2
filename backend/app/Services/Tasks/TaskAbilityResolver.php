<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;

/**
 * THE role -> action matrix (spec 0116, D-1/D-5) from the product document,
 * written in exactly ONE place. App\Policies\TaskPolicy,
 * App\Authorization\TasksAuthorization, App\Services\Tasks\TaskCompletionService and App\Services\Tasks\TaskActionService
 * all read from here; none may duplicate a role condition, so they cannot
 * diverge by construction.
 *
 * Roles SUM (D-3): an actor who is creator/requester AND watcher gets the
 * creator/requester row, the most permissive of the two. The only
 * exception to summing is already folded into
 * TaskRecordRoles::isManager() (D-2's admin-as-assignee deroga).
 *
 * Static and stateless like TaskRecordRoles, for the same
 * `permissions:sync`/zero-argument-Policy constraint.
 */
final class TaskAbilityResolver
{
    /**
     * The 20 fields that define the Task's MANDATE (who answers, by when,
     * about what) rather than its execution (D-5; spec 0121 D-1 added
     * `requires_validation` as the 18th, spec 0120 D-12 added `recurrence` as
     * the 19th, spec 0146 D-3 adds `work_order_stage_id` as the 20th — an
     * assignee who may otherwise edit the Task still may not touch its
     * series, or move it to a different "Fase" of its commessa). Read by
     * `App\Authorization\TasksAuthorization::fieldPermissionCeiling()` to
     * lower the ceiling for anyone who is not creator/requester/manager.
     *
     * @var array<int, string>
     */
    public const array PROTECTED_FIELDS = [
        'title',
        'registry_id',
        'referent_id',
        'parent_task_id',
        'task_type_id',
        'task_priority_id',
        'task_importance_id',
        'task_category_id',
        'opportunity_id',
        'work_order_id',
        'work_order_stage_id',
        'requester_id',
        'start_date',
        'end_date',
        'estimated_minutes',
        'requires_closure_feedback',
        'requires_validation',
        'assignee_ids',
        'watcher_ids',
        'recurrence',
    ];

    /** Creator/requester, assignee and manager may update; the watcher alone may not. */
    public static function canUpdate(User $actor, Task $task): bool
    {
        return self::isCreatorOrRequester($actor, $task)
            || TaskRecordRoles::isAssignee($actor, $task)
            || TaskRecordRoles::isManager($actor, $task);
    }

    /**
     * Spec 0125, D-3: hanging a child under $parent is the same row as
     * editing $parent (the watcher alone may not), named on its own so the
     * guard and the `create_subtask` flag read a rule, not a coincidence.
     */
    public static function canCreateSubtask(User $actor, Task $parent): bool
    {
        return self::canUpdate($actor, $parent);
    }

    /** Only the roles that own the MANDATE may touch a field in PROTECTED_FIELDS. */
    public static function canUpdateProtectedFields(User $actor, Task $task): bool
    {
        return self::ownsTheMandate($actor, $task);
    }

    public static function canDelete(User $actor, Task $task): bool
    {
        return self::ownsTheMandate($actor, $task);
    }

    /**
     * Covers both directions of the completion toggle — complete and
     * uncomplete share the same row of the matrix (creator/requester,
     * assignee, manager; not the watcher).
     */
    public static function canComplete(User $actor, Task $task): bool
    {
        return self::canUpdate($actor, $task);
    }

    public static function canValidate(User $actor, Task $task): bool
    {
        return self::ownsTheMandate($actor, $task);
    }

    /**
     * WHETHER completing $task sends it into validation instead of closing
     * it (spec 0121, D-2, "solo assegnatari"): true only when the Task
     * carries `requires_validation` AND the completing actor does NOT own
     * the mandate. Creator/requester/manager always close directly, even on
     * a flagged Task — they cannot be asked to validate their own mandate.
     * The single source of the percorso derivato: TaskCompletionService,
     * TaskValidationRequirementGuard and TasksAuthorization all call this,
     * never re-derive it (constraints).
     */
    public static function completionRequiresValidation(User $actor, Task $task): bool
    {
        return $task->requires_validation && ! self::ownsTheMandate($actor, $task);
    }

    public static function canBlock(User $actor, Task $task): bool
    {
        return self::ownsTheMandate($actor, $task);
    }

    /**
     * "Richiedi aggiornamento" (spec 0118, D-10): the one row of the matrix
     * with the watcher admitted — creatore/richiedente, watcher and manager,
     * NOT the plain assignee. Deliberately NOT expressed as
     * `ownsTheMandate()` OR `isWatcher()`: the mandate concept (creator/
     * requester/manager) and this row happen to share two of its three
     * terms, but folding the watcher into `ownsTheMandate()` itself would
     * silently grant it to every OTHER mandate-gated action too.
     */
    public static function canRequestUpdate(User $actor, Task $task): bool
    {
        return self::isCreatorOrRequester($actor, $task)
            || TaskRecordRoles::isWatcher($actor, $task)
            || TaskRecordRoles::isManager($actor, $task);
    }

    /**
     * Spec 0126, D-3: a segnatempo filed under $task follows the Task's OWN
     * role matrix instead of `time-entries.manageAll` — an actor who manages
     * every OTHER user's segnatempo across the app still may not touch one
     * on a Task they hold no role on. True for whoever owns the MANDATE
     * (creator/requester/manager) on ANY segnatempo of $task, or for the
     * segnatempo's own owner when they may `canComplete()` $task (i.e. an
     * assignee); false for the watcher, even on their own segnatempo — the
     * one role `canComplete()` excludes.
     */
    public static function canManageTimeEntry(User $actor, Task $task, TimeEntry $entry): bool
    {
        return self::ownsTheMandate($actor, $task)
            || ($entry->user_id === $actor->id && self::canComplete($actor, $task));
    }

    private static function ownsTheMandate(User $actor, Task $task): bool
    {
        return self::isCreatorOrRequester($actor, $task) || TaskRecordRoles::isManager($actor, $task);
    }

    private static function isCreatorOrRequester(User $actor, Task $task): bool
    {
        return TaskRecordRoles::isCreator($actor, $task) || TaskRecordRoles::isRequester($actor, $task);
    }
}
