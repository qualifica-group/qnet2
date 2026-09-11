<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;

/**
 * WHO the actor is on a given Task record (spec 0116, D-1): the four
 * membership roles the product document names, plus the `manageAll`
 * permission read as a fifth role for the matrix's purposes.
 * TaskAbilityResolver reads these to decide WHAT the actor may do; this
 * class only answers WHO they are.
 *
 * Static and stateless like TaskVisibilityScope, for the identical reason:
 * App\Policies\TaskPolicy consumes it and `permissions:sync`
 * (App\Console\Commands\SyncPermissions) instantiates every Policy with
 * `new $class`, without a container — a constructor dependency here would
 * be a fatal error at the first sync, not a style issue.
 */
final class TaskRecordRoles
{
    public static function isCreator(User $actor, Task $task): bool
    {
        return $task->creator_id === $actor->id;
    }

    public static function isRequester(User $actor, Task $task): bool
    {
        return $task->requester_id !== null && $task->requester_id === $actor->id;
    }

    public static function isAssignee(User $actor, Task $task): bool
    {
        if ($task->relationLoaded('assignees')) {
            return $task->assignees->contains('id', $actor->id);
        }

        return $task->assignees()->whereKey($actor->id)->exists();
    }

    public static function isWatcher(User $actor, Task $task): bool
    {
        if ($task->relationLoaded('watchers')) {
            return $task->watchers->contains('id', $actor->id);
        }

        return $task->watchers()->whereKey($actor->id)->exists();
    }

    /**
     * D-2: the "gestore" is `tasks.manageAll` MINUS being an assignee of
     * THIS Task — the document's admin-as-assignee deroga, taken literally:
     * "i permessi dell'admin valgono tutti tranne quando e' stesso l'admin
     * ad essere un assegnatario, in quel caso si comporta come un
     * assegnatario normale". An actor who holds the permission but is also
     * an assignee here loses the manager standing and falls back to
     * whatever genuine role(s) they hold on the record.
     */
    public static function isManager(User $actor, Task $task): bool
    {
        return $actor->can('tasks.manageAll') && ! self::isAssignee($actor, $task);
    }
}
