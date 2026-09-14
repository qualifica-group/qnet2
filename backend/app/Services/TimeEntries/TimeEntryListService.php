<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryDaySummaryData;
use App\DataObjects\TimeEntries\TimeEntryListQuery;
use App\Models\User;

/**
 * GET /api/time-entries (spec 0122, data_contract, AC-010..AC-016, AC-021):
 * day-level filter -> sort -> in-memory pagination on top of
 * `TimeEntryDaySetBuilder`'s single-query day set, plus the response `meta`
 * block.
 */
final class TimeEntryListService
{
    /**
     * @var list<string>
     */
    private const array SORTABLE_FIELDS = ['date', 'target_minutes', 'is_active'];

    public function __construct(
        private readonly TimeEntryDaySetBuilder $daySetBuilder,
        private readonly TimeEntryDayBuilder $dayBuilder,
        private readonly TimeEntrySubordinateResolver $subordinates,
    ) {}

    /**
     * @return array{items: list<TimeEntryDaySummaryData>, total: int, offset: int, meta: array<string, mixed>}
     */
    public function handle(TimeEntryListQuery $query, User $actor): array
    {
        // Step 1: the shared day set (rule R, period, one query for entries + notes).
        $set = $this->daySetBuilder->build($query->filter, $actor);

        // Step 2: day-level filter, then sort, then paginate — in that order.
        $filteredDays = $this->dayBuilder->filterByActivity($set->days, $query->filter);
        $sortedDays = $this->sortDays($filteredDays, $query->sortBy, $query->sortDirection);
        $offset = ($query->page - 1) * $query->perPage;

        return [
            'items' => array_slice($sortedDays, $offset, $query->perPage),
            'total' => count($sortedDays),
            'offset' => $offset,
            'meta' => $this->buildMeta($set->selectedUser, $set->baseTargetMinutes, $actor),
        ];
    }

    /**
     * @param  list<TimeEntryDaySummaryData>  $days
     * @return list<TimeEntryDaySummaryData>
     */
    private function sortDays(array $days, string $sortBy, string $direction): array
    {
        $multiplier = $direction === 'desc' ? -1 : 1;

        usort($days, function (TimeEntryDaySummaryData $a, TimeEntryDaySummaryData $b) use ($sortBy, $multiplier): int {
            $comparison = match ($sortBy) {
                'target_minutes' => $a->targetMinutes <=> $b->targetMinutes,
                'is_active' => (int) $a->isActive <=> (int) $b->isActive,
                default => $a->date <=> $b->date,
            };

            // Tie-break on date ASC regardless of $direction (data_contract).
            return $comparison !== 0 ? $comparison * $multiplier : $a->date <=> $b->date;
        });

        return $days;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMeta(User $selectedUser, int $dailyTargetMinutes, User $actor): array
    {
        $canManageAll = $actor->can('time-entries.manageAll');
        $canViewAllTeam = $actor->can('time-entries.viewAll');
        // Computed ONCE: `viewAll` already implies both can_view and the
        // members_count branch below never needs the descendant list, so the
        // resolver's query only ever runs when it is actually needed.
        $descendantIds = $canViewAllTeam ? [] : $this->subordinates->descendantIds($actor->id);

        return [
            'selected_user' => ['id' => $selectedUser->id, 'name' => $selectedUser->name],
            'daily_target_minutes' => $dailyTargetMinutes,
            'can_write' => $selectedUser->id === $actor->id || $canManageAll,
            'can_filter_users' => $canManageAll,
            'can_export' => $actor->can('time-entries.export'),
            'can_export_monthly' => $actor->can('time-entries.exportMonthly'),
            'team' => [
                'can_view' => $canViewAllTeam || $descendantIds !== [],
                'is_full_list' => $canViewAllTeam,
                'members_count' => $canViewAllTeam
                    ? User::query()->where('is_active', true)->count()
                    : count($descendantIds),
            ],
        ];
    }
}
