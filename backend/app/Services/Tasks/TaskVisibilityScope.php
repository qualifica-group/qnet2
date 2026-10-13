<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use Illuminate\Database\Eloquent\Builder;
use WeakMap;

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
 * Spec 0214 adds the tier `tasks.viewTeam`: the actor also sees every Task
 * with at least one ASSEGNATARIO among their sottoposti, direct or indirect
 * at any depth, deactivated users and deactivated intermediate managers
 * included (TimeEntrySubordinateResolver::allDescendantIds()). Requester,
 * creator or watcher being a sottoposto is not enough. Like `viewSite` it is a
 * UNION with membership and `viewSite`, absorbed by `viewAll`, read-only.
 * The chain is resolved at most once per actor instance (memoized below), as
 * isVisibleTo() runs once per grid row.
 *
 * Spec 0154 D-2 adds `is_private`: a PRIVATE Task narrows ALL the wider tiers at
 * once — `viewAll`, `viewSite` and `viewTeam` see it ONLY through the membership tier,
 * never through the resource permission or the shared-Sede bypass — while
 * leaving the membership tier itself untouched (creator/requester/assignee/
 * watcher always see their own Task, private or not). The super-admin is the
 * one deliberate exception (Gate::before bypasses every ability check, which
 * is why this class cannot tell "true super-admin" from "holds `viewAll`"
 * through `->can()` alone — `hasRole()` is the real, un-bypassed signal),
 * checked FIRST and unconditionally, mirroring RoleAssignmentGuard's own
 * convention for the same role name.
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

    public const string VIEW_TEAM_PERMISSION = 'tasks.viewTeam';

    /** @var WeakMap<User, array<int, int>>|null */
    private static ?WeakMap $teamIdsByActor = null;

    /**
     * @template TModel of Task
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToActor(Builder $query, ?User $user): Builder
    {
        if ($user?->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE)) {
            return $query;
        }

        if ($user === null) {
            return $query->whereNull('tasks.id');
        }

        $hasViewAll = $user->can(self::VIEW_ALL_PERMISSION);
        $siteIds = $hasViewAll ? [] : self::actorSiteIds($user);
        $teamIds = $hasViewAll ? [] : self::actorTeamIds($user);

        return $query->where(function (Builder $scoped) use ($user, $siteIds, $teamIds, $hasViewAll): void {
            $scoped
                ->where('tasks.creator_id', $user->id)
                ->orWhere('tasks.requester_id', $user->id)
                ->orWhereHas('assignees', fn (Builder $assignees) => $assignees->whereKey($user->id))
                ->orWhereHas('watchers', fn (Builder $watchers) => $watchers->whereKey($user->id));

            // D-2 (spec 0154): the wider tiers below see a PRIVATE Task
            // ONLY through the membership branch above — never through
            // `viewAll`, the shared-Sede bypass nor the team tier.
            if ($hasViewAll) {
                $scoped->orWhere('tasks.is_private', false);
            } elseif ($siteIds !== [] || $teamIds !== []) {
                $scoped->orWhere(function (Builder $wide) use ($siteIds, $teamIds): void {
                    $wide->where('tasks.is_private', false)->where(function (Builder $tiers) use ($siteIds, $teamIds): void {
                        if ($siteIds !== []) {
                            $tiers->orWhereHas(
                                'assignees.employment.operationalSites',
                                fn (Builder $sites) => $sites->whereIn('operational_sites.id', $siteIds),
                            );
                        }

                        if ($teamIds !== []) {
                            $tiers->orWhereHas('assignees', fn (Builder $assignees) => $assignees->whereIn('users.id', $teamIds));
                        }
                    });
                });
            }
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
        if ($user->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE)) {
            return true;
        }

        if ($task->creator_id === $user->id || $task->requester_id === $user->id) {
            return true;
        }

        if ($task->relationLoaded('assignees') && $task->relationLoaded('watchers')) {
            if ($task->assignees->contains('id', $user->id) || $task->watchers->contains('id', $user->id)) {
                return true;
            }

            // D-2 (spec 0154): neither wider tier below ever reaches a
            // PRIVATE Task once membership above has already failed.
            if ($task->is_private) {
                return false;
            }

            if ($user->can(self::VIEW_ALL_PERMISSION)) {
                return true;
            }

            if (self::hasTeamAssigneeInMemory($user, $task)) {
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
     * The `viewTeam` tier (spec 0214): the actor's sottoposti ids, empty
     * without the permission so the tier drops out of the predicate. Memoized
     * per actor instance: the rule is asked once per grid row and the chain
     * is a single projection query plus a BFS. A WeakMap (not a static array
     * by id) so the cache dies with the instance and can never leak across
     * requests or tests.
     *
     * @return array<int, int>
     */
    private static function actorTeamIds(User $user): array
    {
        if (! $user->can(self::VIEW_TEAM_PERMISSION)) {
            return [];
        }

        self::$teamIdsByActor ??= new WeakMap;

        return self::$teamIdsByActor[$user] ??= (new TimeEntrySubordinateResolver)->allDescendantIds($user->id);
    }

    private static function hasTeamAssigneeInMemory(User $user, Task $task): bool
    {
        $teamIds = self::actorTeamIds($user);

        return $teamIds !== [] && array_intersect($teamIds, $task->assignees->modelKeys()) !== [];
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
