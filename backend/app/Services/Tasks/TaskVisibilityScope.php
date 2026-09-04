<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single implementation of the Task visibility-by-membership rule (spec
 * 0101, D-9): an actor sees a Task when they hold `tasks.viewAll` (or are
 * super-admin, through the Gate::before bypass) OR they are that Task's
 * CREATORE, RICHIEDENTE, ASSEGNATARIO or OSSERVATORE.
 *
 * The rule NARROWS, it never widens: being an assegnatario does not grant
 * `tasks.view` — the resource permission is still required (AC-062), exactly
 * as `work-orders.viewAll` narrows WorkOrderVisibilityScope.
 *
 * Query shape and record shape are the SAME predicate expressed twice, so
 * the table's rows and the policy's per-record verdict can never disagree:
 * isVisibleTo() falls back to scopeToActor() whenever it cannot answer from
 * already-loaded relations.
 *
 * FAIL-CLOSED (non-negotiable, AC-064): a null actor is scoped to a
 * condition that can never match a row, never left unrestricted — an export
 * generated without an actor contains no rows.
 *
 * Static and stateless like WorkOrderVisibilityScope, for the same two
 * reasons: TasksTableDefinition::baseQuery() calls it inline with
 * `Auth::user()` and no DI wiring, and TaskPolicy must stay zero-argument
 * constructible — `permissions:sync` discovers policies with `new $class`.
 */
final class TaskVisibilityScope
{
    public const string VIEW_ALL_PERMISSION = 'tasks.viewAll';

    /**
     * @template TModel of Task
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToActor(Builder $query, ?User $user): Builder
    {
        if ($user?->can(self::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        if ($user === null) {
            return $query->whereNull('tasks.id');
        }

        return $query->where(function (Builder $scoped) use ($user): void {
            $scoped
                ->where('tasks.creator_id', $user->id)
                ->orWhere('tasks.requester_id', $user->id)
                ->orWhereHas('assignees', fn (Builder $assignees) => $assignees->whereKey($user->id))
                ->orWhereHas('watchers', fn (Builder $watchers) => $watchers->whereKey($user->id));
        });
    }

    /**
     * The same rule for one record. The two scalar branches answer without
     * any database access at all; the in-memory pivot branch is what keeps
     * the table affordable, since TasksTableDefinition asks the Gate once per
     * row and both membership relations are eager-loaded there.
     */
    public static function isVisibleTo(User $user, Task $task): bool
    {
        if ($user->can(self::VIEW_ALL_PERMISSION)) {
            return true;
        }

        if ($task->creator_id === $user->id || $task->requester_id === $user->id) {
            return true;
        }

        if ($task->relationLoaded('assignees') && $task->relationLoaded('watchers')) {
            return $task->assignees->contains('id', $user->id)
                || $task->watchers->contains('id', $user->id);
        }

        return self::scopeToActor(Task::query()->whereKey($task->getKey()), $user)->exists();
    }
}
