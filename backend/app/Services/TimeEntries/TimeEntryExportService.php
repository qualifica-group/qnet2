<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryDaySummaryData;
use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Exports\TimeEntries\FilteredTimeEntrySheetWriter;
use App\Exports\TimeEntries\MonthlyTimeEntrySheetWriter;
use App\Exports\TimeEntries\TimeEntryExportFormatter;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Orchestrates the two xlsx exports (spec 0122, data_contract, MT-B5,
 * AC-024/AC-025): builds a `Spreadsheet` + its filename, leaving HTTP
 * streaming to `TimeEntryExportController`.
 *
 * `filtered()` reuses MT-B3's day-set foundation — the SAME rule R, period
 * resolution and entry/day filters the dashboard list applies (D-3 b:
 * "l'export filtrato applica TUTTI i filtri") — rather than re-querying
 * `time_entries` a second, possibly diverging, way.
 */
final class TimeEntryExportService
{
    public function __construct(
        private readonly TimeEntryDaySetBuilder $daySetBuilder,
        private readonly TimeEntryDayBuilder $dayBuilder,
        private readonly FilteredTimeEntrySheetWriter $filteredWriter,
        private readonly MonthlyTimeEntrySheetWriter $monthlyWriter,
        private readonly TimeEntryExportFormatter $formatter,
    ) {}

    /**
     * @return array{spreadsheet: Spreadsheet, filename: string}
     */
    public function filtered(TimeEntryFilterData $filter, User $actor): array
    {
        // Step 1: rule R + period + entry-level F (TimeEntryDaySetBuilder),
        // then the day-level is_active/daily_statuses filter — identical to
        // TimeEntryListService::handle(), minus sort/pagination.
        $set = $this->daySetBuilder->build($filter, $actor);
        $days = $this->dayBuilder->filterByActivity($set->days, $filter);

        // Step 2: flatten to one row per segnatempo — already date/
        // start_time ordered (days ascending, entries per day start_time
        // asc/null last).
        $entries = collect($days)
            ->flatMap(fn (TimeEntryDaySummaryData $day): Collection => $day->entries)
            ->values();

        $spreadsheet = new Spreadsheet;
        $this->filteredWriter->write($spreadsheet, $set->selectedUser, $this->formatter->periodLabel($set->period), $entries);

        return [
            'spreadsheet' => $spreadsheet,
            'filename' => "segnatempo_{$set->period->dateFrom}_{$set->period->dateTo}.xlsx",
        ];
    }

    /**
     * @param  list<int>  $userIds
     * @return array{spreadsheet: Spreadsheet, filename: string}
     */
    public function monthly(int $month, int $year, array $userIds): array
    {
        $entriesByUser = $this->queryMonthlyEntries($month, $year, $userIds)->groupBy('user_id');

        $spreadsheet = new Spreadsheet;
        $this->monthlyWriter->write($spreadsheet, $entriesByUser, $month, $year);

        return [
            'spreadsheet' => $spreadsheet,
            'filename' => sprintf('report_segnatempo_%04d%02d.xlsx', $year, $month),
        ];
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, TimeEntry>
     */
    private function queryMonthlyEntries(int $month, int $year, array $userIds): Collection
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        return TimeEntry::query()
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($userIds !== [], fn ($query) => $query->whereIn('user_id', $userIds))
            ->with(TimeEntryService::DETAIL_RELATIONS)
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }
}
