<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryDaySet;
use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Models\TimeEntry;
use App\Models\TimeEntryDayNote;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shared foundation of GET /api/time-entries, stats/overview and
 * stats/pulse (spec 0122, MT-B3): resolves rule R and the period, runs
 * EXACTLY one `time_entries` query and one `time_entry_day_notes` query for
 * the whole period, and hands back the full (unfiltered-by-activity) day
 * list — no N+1 regardless of how many days the period spans.
 *
 * @see TimeEntryDayBuilder
 */
final class TimeEntryDaySetBuilder
{
    public function __construct(
        private readonly TimeEntryReadAuthorizer $authorizer,
        private readonly TimeEntryPeriodResolver $periodResolver,
        private readonly TimeEntryDayBuilder $dayBuilder,
        private readonly DailyTargetResolver $targetResolver,
    ) {}

    public function build(TimeEntryFilterData $filter, User $actor): TimeEntryDaySet
    {
        // Step 1: rule R — who this read is FOR.
        $selectedUser = $this->authorizer->resolveSelectedUser($filter->userId, $actor);
        $selectedUser->loadMissing('employment');

        // Step 2: the period, and that user's calendar-agnostic target (D-7).
        $period = $this->periodResolver->resolve($filter);
        $baseTargetMinutes = $this->targetResolver->resolve($selectedUser->employment);

        // Step 3: the ONLY two queries this whole read path runs.
        $entries = $this->queryEntries($selectedUser, $period->dateFrom, $period->dateTo, $filter);
        $notesByDate = $this->queryNotesByDate($selectedUser, $period->dateFrom, $period->dateTo);

        // Step 4: one DaySummary per date, entries already F-filtered.
        $days = $this->dayBuilder->build($period->dates(), $entries, $notesByDate, $baseTargetMinutes);

        return new TimeEntryDaySet($selectedUser, $period, $baseTargetMinutes, $days);
    }

    /**
     * @return Collection<int, TimeEntry>
     */
    private function queryEntries(User $selectedUser, string $dateFrom, string $dateTo, TimeEntryFilterData $filter): Collection
    {
        return TimeEntry::query()
            ->where('user_id', $selectedUser->id)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($filter->taskTypeIds !== [], fn ($query) => $query->whereIn('task_type_id', $filter->taskTypeIds))
            ->when($filter->registryIds !== [], fn ($query) => $query->whereIn('registry_id', $filter->registryIds))
            ->when($filter->opportunityIds !== [], fn ($query) => $query->whereIn('opportunity_id', $filter->opportunityIds))
            ->when($filter->workOrderIds !== [], fn ($query) => $query->whereIn('work_order_id', $filter->workOrderIds))
            ->when($filter->taskIds !== [], fn ($query) => $query->whereIn('task_id', $filter->taskIds))
            ->with(TimeEntryService::DETAIL_RELATIONS)
            ->get();
    }

    /**
     * @return array<string, string>
     */
    private function queryNotesByDate(User $selectedUser, string $dateFrom, string $dateTo): array
    {
        return TimeEntryDayNote::query()
            ->where('user_id', $selectedUser->id)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get(['date', 'note'])
            ->mapWithKeys(static fn (TimeEntryDayNote $note): array => [$note->date->format('Y-m-d') => $note->note])
            ->all();
    }
}
