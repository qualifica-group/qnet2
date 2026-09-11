<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;

/**
 * THE role -> action matrix (spec 0116, D-1/D-5) from the product document,
 * written in exactly ONE place. App\Policies\TaskPolicy,
 * App\Authorization\TasksAuthorization and App\Services\TaskActionService
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
     * The 17 fields that define the Task's MANDATE (who answers, by when,
     * about what) rather than its execution (D-5). Read by
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
        'requester_id',
        'start_date',
        'end_date',
        'estimated_minutes',
        'requires_closure_feedback',
        'assignee_ids',
        'watcher_ids',
    ];

    /** Creator/requester, assignee and manager may update; the watcher alone may not. */
    public static function canUpdate(User $actor, Task $task): bool
    {
        return self::isCreatorOrRequester($actor, $task)
            || TaskRecordRoles::isAssignee($actor, $task)
            || TaskRecordRoles::isManager($actor, $task);
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

    public static function canBlock(User $actor, Task $task): bool
    {
        return self::ownsTheMandate($actor, $task);
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
