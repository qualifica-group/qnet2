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
        $byManagerId = $this->activeReportsByManagerId();

        $ids = [];
        $visited = [$managerId => true];
        $queue = $this->subordinateIds($byManagerId, $managerId);

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $ids[] = $currentId;

            array_push($queue, ...$this->subordinateIds($byManagerId, $currentId));
        }

        return $ids;
    }

    public function isDescendantOf(int $userId, int $managerId): bool
    {
        return in_array($userId, $this->descendantIds($managerId), true);
    }

    /**
     * One projection query joining the `employment_profile_manager` pivot to
     * `employment_profiles`/`users`: manager user_id => direct reports' rows
     * — active users only (D-10), a subordinate profile counted once per
     * manager it lists (D-7).
     *
     * @return Collection<int|string, Collection<int, object{manager_id: int, subordinate_id: int}>>
     */
    private function activeReportsByManagerId(): Collection
    {
        return DB::table('employment_profile_manager')
            ->join('employment_profiles', 'employment_profiles.id', '=', 'employment_profile_manager.employment_profile_id')
            ->join('users', 'users.id', '=', 'employment_profiles.user_id')
            ->where('users.is_active', true)
            ->get(['employment_profile_manager.user_id as manager_id', 'employment_profiles.user_id as subordinate_id'])
            ->groupBy('manager_id');
    }

    /**
     * @param  Collection<int|string, Collection<int, object{manager_id: int, subordinate_id: int}>>  $byManagerId
     * @return list<int>
     */
    private function subordinateIds(Collection $byManagerId, int $managerId): array
    {
        return $byManagerId->get($managerId, collect())->pluck('subordinate_id')->map(intval(...))->all();
    }
}
