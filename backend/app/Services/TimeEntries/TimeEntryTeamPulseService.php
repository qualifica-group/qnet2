<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\DataObjects\TimeEntries\TimeEntryPeriod;
use App\DataObjects\TimeEntries\TimeEntryTeamMemberData;
use App\Models\BusinessFunction;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * GET /api/time-entries/stats/team (spec 0122, D-10, AC-019/AC-020): WHO the
 * members are (a responsabile's active descendants, or every active user
 * with `viewAll`, D-8/D-10), each paired with the SAME pulse formula (D-11)
 * `TimeEntryStatsService::pulse()` applies to a single user — batched here
 * across the whole team instead, so no member triggers its own pair of
 * queries (no N+1 as the team grows). The cluster half of that formula is
 * `TimeEntryClusterCalculator`, shared verbatim with `TimeEntryStatsService`
 * so the two can never drift.
 *
 * Deliberately NOT built on `TimeEntryDaySetBuilder`: that builder
 * authorizes/queries ONE selected user at a time, which is exactly the shape
 * that would turn this endpoint into N query pairs for N members. This
 * composes the same lower-level collaborators (`TimeEntrySubordinateResolver`,
 * `TimeEntryPeriodResolver`, `WorkCalendar`, `DailyTargetResolver`,
 * `TimeEntryClusterCalculator`) instead, batched over the whole member set.
 */
final class TimeEntryTeamPulseService
{
    public function __construct(
        private readonly TimeEntrySubordinateResolver $subordinates,
        private readonly TimeEntryPeriodResolver $periodResolver,
        private readonly WorkCalendar $calendar,
        private readonly DailyTargetResolver $targetResolver,
        private readonly TimeEntryClusterCalculator $clusterCalculator,
    ) {}

    /**
     * @return array{is_full_list: bool, items: list<TimeEntryTeamMemberData>}
     */
    public function handle(TimeEntryFilterData $filter, User $actor): array
    {
        // Step 1: WHO — authorize and resolve the member set (D-8, D-10).
        [$memberIds, $isFullList] = $this->resolveMemberIds($actor);

        // Step 2: the period's working dates, shared by every member (D-11:
        // pulse runs on the period's working days alone, IGNORA daily
        // statuses/is_active).
        $period = $this->periodResolver->resolve($filter);
        $workingDates = $this->workingDates($period);

        // Step 3: batch-load members + their org data + their entries — a
        // FIXED number of queries regardless of how many members there are.
        $members = $this->loadMembers($memberIds);
        $entriesByUserId = $this->loadEntriesByUserId($memberIds, $period);
        $businessFunctionsByUserId = $this->loadBusinessFunctionsByUserId($memberIds);

        // Step 4: one TimeEntryTeamMemberData per member, pure in-memory
        // math off the batches above.
        $items = $members
            ->map(fn (User $member): TimeEntryTeamMemberData => $this->buildMember(
                $member,
                $workingDates,
                $entriesByUserId->get($member->id, collect()),
                $businessFunctionsByUserId->get($member->id, []),
            ))
            ->all();

        return ['is_full_list' => $isFullList, 'items' => $items];
    }

    /**
     * @return array{0: list<int>, 1: bool}
     */
    private function resolveMemberIds(User $actor): array
    {
        abort_unless($actor->can('time-entries.viewAny'), 403);

        if ($actor->can('time-entries.viewAll')) {
            return [User::query()->where('is_active', true)->pluck('id')->all(), true];
        }

        $descendantIds = $this->subordinates->descendantIds($actor->id);
        abort_if($descendantIds === [], 403);

        return [$descendantIds, false];
    }

    /**
     * @return list<string>
     */
    private function workingDates(TimeEntryPeriod $period): array
    {
        return array_values(array_filter(
            $period->dates(),
            fn (string $date): bool => ! $this->calendar->isNonWorkingDay($date),
        ));
    }

    /**
     * `employment.primaryOperationalSite` nested eager load: the profile
     * (target/manager_id/job_description) and its at-most-one physical site
     * (D-3) in the same round trip as `roles`/`avatar`, so the whole member
     * list costs exactly 5 queries (users, employment, primaryOperationalSite,
     * roles, avatar) no matter how many members are in $memberIds.
     *
     * @param  list<int>  $memberIds
     * @return Collection<int, User>
     */
    private function loadMembers(array $memberIds): Collection
    {
        return User::query()
            ->whereIn('id', $memberIds)
            ->with(['employment.primaryOperationalSite', 'roles', 'avatar'])
            ->orderBy('name')
            ->get();
    }

    /**
     * One query for the whole period's entries (`whereBetween`, mirroring
     * `TimeEntryDaySetBuilder::queryEntries`) plus one for `taskType` eager
     * loading — the non-working-day exclusion (D-11) happens in
     * `buildMember()` off this same batch, not with a second query.
     *
     * @param  list<int>  $memberIds
     * @return Collection<int, Collection<int, TimeEntry>> keyed by user_id
     */
    private function loadEntriesByUserId(array $memberIds, TimeEntryPeriod $period): Collection
    {
        return TimeEntry::query()
            ->whereIn('user_id', $memberIds)
            ->whereBetween('date', [$period->dateFrom, $period->dateTo])
            ->with('taskType')
            ->get()
            ->groupBy('user_id');
    }

    /**
     * Batches `business_functions`/`business_function_user` in exactly two
     * queries (the functions row set, then their member rows) instead of one
     * pair per member — `is_manager` reads off `business_functions.
     * manager_id` (the FUNCTION's manager), not a pivot column.
     *
     * @param  list<int>  $memberIds
     * @return Collection<int, list<array{id: int, name: string, is_manager: bool}>> keyed by user_id
     */
    private function loadBusinessFunctionsByUserId(array $memberIds): Collection
    {
        $functions = BusinessFunction::query()
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $memberIds))
            ->with(['users' => fn ($query) => $query->select('users.id')->whereIn('users.id', $memberIds)])
            ->get(['id', 'name', 'manager_id']);

        $byUserId = [];

        foreach ($functions as $function) {
            foreach ($function->users as $user) {
                $byUserId[$user->id][] = [
                    'id' => $function->id,
                    'name' => $function->name,
                    'is_manager' => $function->manager_id === $user->id,
                ];
            }
        }

        return collect($byUserId);
    }

    /**
     * @param  list<string>  $workingDates
     * @param  Collection<int, TimeEntry>  $entries  the member's WHOLE-period entries, pre-grouped
     * @param  list<array{id: int, name: string, is_manager: bool}>  $businessFunctions
     */
    private function buildMember(User $member, array $workingDates, Collection $entries, array $businessFunctions): TimeEntryTeamMemberData
    {
        $workingDateSet = array_flip($workingDates);
        $workingEntries = $entries->filter(
            static fn (TimeEntry $entry): bool => isset($workingDateSet[$entry->date->format('Y-m-d')]),
        );

        $targetMinutes = $this->targetResolver->resolve($member->employment) * count($workingDates);
        $trackedMinutes = (int) $workingEntries->sum('minutes');
        $trackedDays = $workingEntries
            ->map(static fn (TimeEntry $entry): string => $entry->date->format('Y-m-d'))
            ->unique()
            ->count();

        return new TimeEntryTeamMemberData(
            user: $member,
            managerId: $member->employment?->reports_to_id,
            jobDescription: $member->employment?->job_description,
            businessFunctions: $businessFunctions,
            operationalSite: $member->employment?->primaryOperationalSite->first(),
            coverage: [
                'percentage' => $targetMinutes > 0 ? (int) round($trackedMinutes / $targetMinutes * 100) : 0,
                'working_days' => count($workingDates),
                'tracked_days' => $trackedDays,
            ],
            primaryCluster: $this->clusterCalculator->calculate($workingEntries, $trackedMinutes)[0] ?? null,
        );
    }
}
