<?php

declare(strict_types=1);

namespace App\Exports\TimeEntries;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the "Segnatempo" sheet of GET /api/time-entries/exports/filtered
 * (spec 0122, data_contract, AC-024): title/user/period header, then three
 * stacked tables — carico per tipo (with a "Tot. hh:mm" total row), carico
 * per commessa (entries with a work order only), panoramica (one row per
 * segnatempo). Percentages in both load sections share the SAME
 * denominator — total minutes of every entry handed in — mirroring qnet's
 * own `WorkingActivityExport::prepareData()`.
 *
 * $entries is assumed already rule-R/F/day-filtered and date/start_time
 * ordered by the caller (`TimeEntryExportService`); this class runs no
 * queries and applies no filter of its own.
 */
final class FilteredTimeEntrySheetWriter
{
    private const string SHEET_NAME = 'Segnatempo';

    private const string TITLE = 'Rapporto segnatempo';

    private const string SECTION_BY_TYPE = 'Carico di lavoro per tipo';

    private const string SECTION_BY_WORK_ORDER = 'Carico di lavoro per commessa';

    private const string SECTION_OVERVIEW = 'Panoramica dei segnatempo';

    private const string TOTAL_LABEL = 'Tot. hh:mm';

    private const string NO_DATA_PLACEHOLDER = '-';

    /**
     * @var list<string>
     */
    private const array TYPE_HEADERS = ['Tipo', 'Tot. hh:mm', '%'];

    /**
     * @var list<string>
     */
    private const array WORK_ORDER_HEADERS = ['Cliente', 'Commessa', 'Tot. hh:mm', '%'];

    /**
     * @var list<string>
     */
    private const array OVERVIEW_HEADERS = [
        'Data', 'Dalle', 'Alle', 'Durata hh:mm', 'Tipo', 'Titolo',
        'Cliente', 'Opportunità', 'Commessa', 'Task', 'Note',
    ];

    /**
     * @var array<string, int>
     */
    private const array COLUMN_WIDTHS = [
        'A' => 14, 'B' => 14, 'C' => 12, 'D' => 12, 'E' => 14, 'F' => 30,
        'G' => 20, 'H' => 20, 'I' => 28, 'J' => 20, 'K' => 35,
    ];

    public function __construct(private readonly TimeEntryExportFormatter $formatter) {}

    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    public function write(Spreadsheet $spreadsheet, User $user, string $periodLabel, Collection $entries): void
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle(self::SHEET_NAME);

        $row = $this->writeHeader($worksheet, $user->name, $periodLabel);
        $totalMinutes = (int) $entries->sum('minutes');

        $row = $this->writeTypeSection($worksheet, $row, $entries, $totalMinutes) + 2;

        $workOrderResult = $this->writeTable($worksheet, $row, self::SECTION_BY_WORK_ORDER, self::WORK_ORDER_HEADERS, $this->buildWorkOrderRows($entries, $totalMinutes));
        $row = $workOrderResult['nextRow'] + 1;

        $overview = $this->writeTable($worksheet, $row, self::SECTION_OVERVIEW, self::OVERVIEW_HEADERS, $this->buildOverviewRows($entries));

        $this->applyColumnWidths($worksheet);
        $this->applyFreezeAndFilter($worksheet, $overview);
    }

    private function writeHeader(Worksheet $worksheet, string $userName, string $periodLabel): int
    {
        $worksheet->mergeCells('A1:K1');
        $worksheet->setCellValue('A1', self::TITLE);
        TimeEntryExportStyles::applyTitle($worksheet, 'A1:K1');
        $worksheet->getRowDimension(1)->setRowHeight(TimeEntryExportStyles::TITLE_ROW_HEIGHT);

        $worksheet->mergeCells('A2:K2');
        $worksheet->setCellValue('A2', $userName);
        TimeEntryExportStyles::applySubtitle($worksheet, 'A2:K2');
        $worksheet->getRowDimension(2)->setRowHeight(TimeEntryExportStyles::SUBTITLE_ROW_HEIGHT);

        $worksheet->mergeCells('A3:K3');
        $worksheet->setCellValue('A3', $periodLabel);
        TimeEntryExportStyles::applySubtitle($worksheet, 'A3:K3');
        $worksheet->getRowDimension(3)->setRowHeight(TimeEntryExportStyles::SUBTITLE_ROW_HEIGHT);

        return 5; // Row 4 stays blank as a spacer.
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return int the row the "Tot. hh:mm" total row was written to
     */
    private function writeTypeSection(Worksheet $worksheet, int $row, Collection $entries, int $totalMinutes): int
    {
        $result = $this->writeTable($worksheet, $row, self::SECTION_BY_TYPE, self::TYPE_HEADERS, $this->buildTypeRows($entries, $totalMinutes));

        $totalRow = $result['nextRow'];
        $worksheet->setCellValue([1, $totalRow], self::TOTAL_LABEL);
        $worksheet->setCellValue([2, $totalRow], $this->formatter->minutesToHhMm($totalMinutes));
        TimeEntryExportStyles::applyTotalRow($worksheet, "A{$totalRow}:C{$totalRow}");
        $worksheet->getRowDimension($totalRow)->setRowHeight(TimeEntryExportStyles::TOTAL_ROW_HEIGHT);

        return $totalRow;
    }

    /**
     * Writes ONE section (title banner + header row + data/placeholder
     * rows) starting at $row, returning the bookkeeping callers need: the
     * next free row, and the header/last-data row for freeze pane/autofilter.
     *
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @return array{nextRow: int, headerRow: int, lastDataRow: int}
     */
    private function writeTable(Worksheet $worksheet, int $row, string $title, array $headers, array $rows): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $worksheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $worksheet->setCellValue("A{$row}", $title);
        TimeEntryExportStyles::applySectionHeader($worksheet, "A{$row}:{$lastColumn}{$row}");
        $row++;

        foreach ($headers as $index => $label) {
            $worksheet->setCellValue([$index + 1, $row], $label);
        }
        TimeEntryExportStyles::applyTableHeader($worksheet, "A{$row}:{$lastColumn}{$row}");
        $headerRow = $row;
        $row++;

        $dataRows = $rows === [] ? [array_fill(0, count($headers), self::NO_DATA_PLACEHOLDER)] : $rows;

        foreach ($dataRows as $index => $values) {
            foreach ($values as $columnIndex => $value) {
                $worksheet->setCellValue([$columnIndex + 1, $row], $value);
            }
            TimeEntryExportStyles::applyBodyRow($worksheet, "A{$row}:{$lastColumn}{$row}", $index % 2 === 0);
            $row++;
        }

        return ['nextRow' => $row, 'headerRow' => $headerRow, 'lastDataRow' => $row - 1];
    }

    /**
     * @param  array{nextRow: int, headerRow: int, lastDataRow: int}  $overview
     */
    private function applyFreezeAndFilter(Worksheet $worksheet, array $overview): void
    {
        $worksheet->freezePane('A'.($overview['headerRow'] + 1));

        if ($overview['lastDataRow'] >= $overview['headerRow'] + 1) {
            $lastColumn = Coordinate::stringFromColumnIndex(count(self::OVERVIEW_HEADERS));
            $worksheet->setAutoFilter('A'.$overview['headerRow'].':'.$lastColumn.$overview['lastDataRow']);
        }
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return list<list<string>>
     */
    private function buildTypeRows(Collection $entries, int $totalMinutes): array
    {
        return $entries
            ->groupBy(fn (TimeEntry $entry): int => $entry->task_type_id)
            ->map(function (Collection $group) use ($totalMinutes): array {
                $minutes = (int) $group->sum('minutes');
                $percentage = $totalMinutes > 0 ? round($minutes / $totalMinutes * 100, 2) : 0.0;

                return [
                    'minutes' => $minutes,
                    'row' => [$group->first()->taskType->name, $this->formatter->minutesToHhMm($minutes), $this->formatter->percentageLabel($percentage)],
                ];
            })
            ->sortByDesc('minutes')
            ->pluck('row')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return list<list<string>>
     */
    private function buildWorkOrderRows(Collection $entries, int $totalMinutes): array
    {
        return $entries
            ->filter(fn (TimeEntry $entry): bool => $entry->work_order_id !== null)
            ->groupBy(fn (TimeEntry $entry): int => $entry->work_order_id)
            ->map(function (Collection $group) use ($totalMinutes): array {
                $first = $group->first();
                $minutes = (int) $group->sum('minutes');
                $percentage = $totalMinutes > 0 ? round($minutes / $totalMinutes * 100, 2) : 0.0;

                return [
                    'minutes' => $minutes,
                    'row' => [
                        $this->formatter->textOrPlaceholder($first->registry?->name),
                        $first->workOrder->code.' - '.$first->workOrder->title,
                        $this->formatter->minutesToHhMm($minutes),
                        $this->formatter->percentageLabel($percentage),
                    ],
                ];
            })
            ->sortByDesc('minutes')
            ->pluck('row')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return list<list<string>>
     */
    private function buildOverviewRows(Collection $entries): array
    {
        return $entries->map(fn (TimeEntry $entry): array => [
            $entry->date->format('d/m/Y'),
            $this->formatter->timeLabel($entry->start_time),
            $this->formatter->timeLabel($entry->end_time),
            $this->formatter->minutesToHhMm($entry->minutes),
            $entry->taskType->name,
            $entry->title,
            $this->formatter->textOrPlaceholder($entry->registry?->name),
            $this->formatter->textOrPlaceholder($entry->opportunity?->name),
            $entry->workOrder !== null ? $entry->workOrder->code.' - '.$entry->workOrder->title : self::NO_DATA_PLACEHOLDER,
            $this->formatter->textOrPlaceholder($entry->task?->title),
            $this->formatter->textOrPlaceholder($entry->notes),
        ])->values()->all();
    }

    private function applyColumnWidths(Worksheet $worksheet): void
    {
        foreach (self::COLUMN_WIDTHS as $column => $width) {
            $worksheet->getColumnDimension($column)->setWidth($width);
        }
    }
}
