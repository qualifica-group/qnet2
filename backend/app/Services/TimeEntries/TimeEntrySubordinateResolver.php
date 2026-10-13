<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * THE single implementation of "who reports up to this manager, recursively"
 * (spec 0122, D-10; spec 0166 D-7): active users only, walking the
 * `employment_profile_manager` pivot down from a manager — a member with
 * SEVERAL managers is reachable through every one of them (D-7: each manager
 * and its own upward chain sees the member as today), while the BFS `visited`
 * set still returns each reachable id ONCE regardless of how many paths reach
 * it. Shared by `TimeEntryReadAuthorizer` (rule R: a responsabile reads a
 * sottoposto's dashboard, AC-016/AC-011) and reused as-is by the team
 * endpoint (MT-B4) — this is the ONE place both may ever call to answer
 * "is X a descendant of Y".
 *
 * In-memory BFS off a single grouped projection query, mirrors
 * `CategoryHierarchy::descendantIds()`. A `visited` set breaks any cycle in
 * the data (a corrupted manager chain looping back on itself) after at most
 * one pass per user — no depth cap needed, unlike the ancestor climbs in
 * `CategoryHierarchy`, which lack a visited set of their own.
 */
final class TimeEntrySubordinateResolver
{
    /**
     * Every ACTIVE descendant user id of $managerId (excludes $managerId
     * itself).
     *
     * @return list<int>
     */
    public function descendantIds(int $managerId): array
    {
        return $this->walk($this->edges(activeOnly: true)->groupBy('manager_id'), 'subordinate_id', [$managerId], [$managerId => true]);
    }

    public function isDescendantOf(int $userId, int $managerId): bool
    {
        return in_array($userId, $this->descendantIds($managerId), true);
    }

    /**
     * Spec 0214 D-4: the same walk as descendantIds() over EVERY user,
     * deactivated ones included, and through deactivated intermediate
     * managers too. Used by the Task team visibility tier; the segnatempo
     * keep the active-only variant above.
     *
     * @return list<int>
     */
    public function allDescendantIds(int $managerId): array
    {
        return $this->walk($this->edges(activeOnly: false)->groupBy('manager_id'), 'subordinate_id', [$managerId], [$managerId => true]);
    }

    /**
     * Spec 0214 D-9: every user id standing above ANY of $userIds, at any
     * level, deactivated included. A start id is returned only when it is
     * itself above another start id (or through a data cycle).
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function allAncestorIds(array $userIds): array
    {
        return $this->walk($this->edges(activeOnly: false)->groupBy('subordinate_id'), 'manager_id', $userIds, []);
    }

    /**
     * BFS over an adjacency map, returning each reachable id once. The
     * `visited` set breaks any cycle in the data after one pass per user.
     *
     * @param  Collection<int|string, Collection<int, object{manager_id: int, subordinate_id: int}>>  $adjacency
     * @param  list<int>  $startIds
     * @param  array<int, true>  $visited
     * @return list<int>
     */
    private function walk(Collection $adjacency, string $column, array $startIds, array $visited): array
    {
        $ids = [];
        $queue = $this->neighbourIds($adjacency, $column, $startIds);

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $ids[] = $currentId;

            array_push($queue, ...$this->neighbourIds($adjacency, $column, [$currentId]));
        }

        return $ids;
    }

    /**
     * One projection query joining the `employment_profile_manager` pivot to
     * `employment_profiles` (and `users` for the active-only variant, D-10):
     * one row per manager/subordinate edge, a subordinate profile counted
     * once per manager it lists (D-7).
     *
     * @return Collection<int, object{manager_id: int, subordinate_id: int}>
     */
    private function edges(bool $activeOnly): Collection
    {
        return DB::table('employment_profile_manager')
            ->join('employment_profiles', 'employment_profiles.id', '=', 'employment_profile_manager.employment_profile_id')
            ->when($activeOnly, fn ($query) => $query
                ->join('users', 'users.id', '=', 'employment_profiles.user_id')
                ->where('users.is_active', true))
            ->get(['employment_profile_manager.user_id as manager_id', 'employment_profiles.user_id as subordinate_id']);
    }

    /**
     * @param  Collection<int|string, Collection<int, object{manager_id: int, subordinate_id: int}>>  $adjacency
     * @param  list<int>  $fromIds
     * @return list<int>
     */
    private function neighbourIds(Collection $adjacency, string $column, array $fromIds): array
    {
        $ids = [];

        foreach ($fromIds as $fromId) {
            array_push($ids, ...$adjacency->get($fromId, collect())->pluck($column)->map(intval(...))->all());
        }

        return $ids;
    }
}
