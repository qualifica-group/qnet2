<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

use App\Models\OperationalSite;
use App\Models\User;

/**
 * One row of GET /api/time-entries/stats/team (spec 0122, D-10/D-11,
 * AC-019/AC-020): a member's identity/org data plus the SAME pulse formula
 * (`coverage` + `primary_cluster`) `TimeEntryStatsService::pulse()` computes
 * for a single user, here batched across the whole team by
 * `TimeEntryTeamPulseService` — see that class for the no-N+1 story.
 */
final readonly class TimeEntryTeamMemberData
{
    /**
     * @param  list<array{id: int, name: string, is_manager: bool}>  $businessFunctions
     * @param  array{percentage: int, working_days: int, tracked_days: int}  $coverage
     * @param  array{task_type: array{id: int, name: string, color: ?string, icon: ?string}, minutes: int, percentage: int}|null  $primaryCluster
     */
    public function __construct(
        public User $user,
        public ?int $managerId,
        public ?string $jobDescription,
        public array $businessFunctions,
        public ?OperationalSite $operationalSite,
        public array $coverage,
        public ?array $primaryCluster,
    ) {}
}
