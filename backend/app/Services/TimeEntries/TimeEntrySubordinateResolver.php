<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\Models\EmploymentProfile;
use Illuminate\Support\Collection;

/**
 * THE single implementation of "who reports up to this manager, recursively"
 * (spec 0122, D-10): active users only, walking
 * `employment_profiles.reports_to_id` down from a manager. Shared by
 * `TimeEntryReadAuthorizer` (rule R: a responsabile reads a sottoposto's
 * dashboard, AC-016) and reused as-is by the team endpoint (MT-B4) — this is
 * the ONE place both may ever call to answer "is X a descendant of Y".
 *
 * In-memory BFS off a single grouped projection query, mirrors
 * `CategoryHierarchy::descendantIds()`. A `visited` set breaks any cycle in
 * the data (a corrupted `reports_to_id` chain looping back on itself) after
 * at most one pass per user — no depth cap needed, unlike the ancestor climbs
 * in `CategoryHierarchy`, which lack a visited set of their own.
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
     * One projection query, `reports_to_id` (manager) => direct reports'
     * profiles — active users only (D-10).
     *
     * @return Collection<int|string, Collection<int, EmploymentProfile>>
     */
    private function activeReportsByManagerId(): Collection
    {
        return EmploymentProfile::query()
            ->select('user_id', 'reports_to_id')
            ->whereNotNull('reports_to_id')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->get()
            ->groupBy('reports_to_id');
    }

    /**
     * @param  Collection<int|string, Collection<int, EmploymentProfile>>  $byManagerId
     * @return list<int>
     */
    private function subordinateIds(Collection $byManagerId, int $managerId): array
    {
        return $byManagerId->get($managerId, collect())->pluck('user_id')->map(intval(...))->all();
    }
}
