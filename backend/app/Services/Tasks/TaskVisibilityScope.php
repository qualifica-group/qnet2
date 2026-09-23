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
 * Spec 0148 adds the middle tier `tasks.viewSite`: the actor also sees every
 * Task with at least one ASSEGNATARIO sharing one of the actor's Sedi
 * operative, physical or remote alike (the same membership set as
 * RequestManagementScope's `viewSite`, spec 0105 D-4). It is a UNION with the
 * membership tier, never a replacement, and a ROW gate only: what may be done
 * to the Task is still decided by TaskAbilityResolver's record-role matrix.
 * A Task with no assignee, or an actor with no Sede, never matches the tier.
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

    public const string VIEW_SITE_PERMISSION = 'tasks.viewSite';

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

        $siteIds = self::actorSiteIds($user);

        return $query->where(function (Builder $scoped) use ($user, $siteIds): void {
            $scoped
                ->where('tasks.creator_id', $user->id)
                ->orWhere('tasks.requester_id', $user->id)
                ->orWhereHas('assignees', fn (Builder $assignees) => $assignees->whereKey($user->id))
                ->orWhereHas('watchers', fn (Builder $watchers) => $watchers->whereKey($user->id))
                ->when($siteIds !== [], fn (Builder $bySite) => $bySite->orWhereHas(
                    'assignees.employment.operationalSites',
                    fn (Builder $sites) => $sites->whereIn('operational_sites.id', $siteIds),
                ));
        });
    }

    /**
     * The same rule for one record. The two scalar branches answer without
     * any database access at all; the in-memory branches are what keep the
     * table affordable, since TasksTableDefinition asks the Gate once per
     * row and both membership relations (plus the assignees' Sedi) are
     * eager-loaded there.
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
            if ($task->assignees->contains('id', $user->id) || $task->watchers->contains('id', $user->id)) {
                return true;
            }

            $sharesSite = self::sharesSiteInMemory($user, $task);

            if ($sharesSite !== null) {
                return $sharesSite;
            }
        }

        return self::scopeToActor(Task::query()->whereKey($task->getKey()), $user)->exists();
    }

    /**
     * The actor's Sedi for the `viewSite` tier: empty without the permission,
     * so the tier simply drops out of the predicate. Physical and remote
     * alike, through the pivot relation (spec 0103 D-1). `loadMissing`
     * because the authenticated actor arrives with no relations loaded and
     * lazy loading is forbidden outside production (backend.md §3); the
     * per-row callers pay for it once per request.
     *
     * @return array<int, int>
     */
    private static function actorSiteIds(User $user): array
    {
        if (! $user->can(self::VIEW_SITE_PERMISSION)) {
            return [];
        }

        return $user->loadMissing('employment.operationalSites')
            ->employment?->operationalSites->pluck('id')->all() ?? [];
    }

    /**
     * The `viewSite` tier answered from `assignees.employment.operationalSites`
     * when every level is already loaded, null when it is not (the caller
     * then falls back to the query shape, never guesses).
     */
    private static function sharesSiteInMemory(User $user, Task $task): ?bool
    {
        $siteIds = self::actorSiteIds($user);

        if ($siteIds === []) {
            return false;
        }

        $assigneeSiteIds = [];

        foreach ($task->assignees as $assignee) {
            if (! $assignee->relationLoaded('employment')) {
                return null;
            }

            $employment = $assignee->employment;

            if ($employment === null) {
                continue;
            }

            if (! $employment->relationLoaded('operationalSites')) {
                return null;
            }

            array_push($assigneeSiteIds, ...$employment->operationalSites->pluck('id')->all());
        }

        return array_intersect($siteIds, $assigneeSiteIds) !== [];
    }
}
