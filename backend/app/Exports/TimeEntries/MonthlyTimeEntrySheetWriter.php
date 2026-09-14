<?php

declare(strict_types=1);

namespace App\Exports\TimeEntries;

use App\Models\TimeEntry;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds GET /api/time-entries/exports/monthly (spec 0122, data_contract,
 * AC-025): one sheet per user, rebuilding qnet's own report mensile layout
 * (`WorkActivityService::populateMonthlyReportWorksheet`, legacy backend)
 * from code — same palette as the filtered export (`TimeEntryExportStyles`),
 * same column widths as the qnet original (D-12: no template/logo).
 *
 * $entriesByUser is assumed already scoped to the requested month/users and
 * DETAIL_RELATIONS-eager-loaded by the caller (`TimeEntryExportService`).
 */
final class MonthlyTimeEntrySheetWriter
{
    private const string EMPTY_SHEET_TITLE = 'Nessun dato';

    private const int SHEET_NAME_MAX_LENGTH = 31;

    private const string INVALID_SHEET_NAME_CHARS = '/[\\\\\/:*?\[\]]/';

    /**
     * @var list<string>
     */
    private const array HEADERS = ['Data', 'Titolo attività', 'Tipo', 'Cliente', 'Orario inizio', 'Orario fine', 'Durata (min)', 'Note'];

    /**
     * @var array<string, int>
     */
    private const array COLUMN_WIDTHS = ['A' => 12, 'B' => 40, 'C' => 12, 'D' => 25, 'E' => 10, 'F' => 10, 'G' => 11, 'H' => 45];

    public function __construct(private readonly TimeEntryExportFormatter $formatter) {}

    /**
     * @param  Collection<int, Collection<int, TimeEntry>>  $entriesByUser  keyed by user_id
     */
    public function write(Spreadsheet $spreadsheet, Collection $entriesByUser, int $month, int $year): void
    {
        if ($entriesByUser->isEmpty()) {
            $this->writeEmptySheet($spreadsheet->getActiveSheet(), $month, $year);

            return;
        }

        $sortedGroups = $entriesByUser->sortBy(fn (Collection $entries): string => $entries->first()->user->name);

        $usedSheetNames = [];
        $sheetIndex = 0;

        foreach ($sortedGroups as $entries) {
            $worksheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $userName = $entries->first()->user->name;
            $worksheet->setTitle($this->uniqueSheetName($userName, $usedSheetNames));

            $this->populateUserSheet($worksheet, $entries, $userName, $month, $year);
            $sheetIndex++;
        }
    }

    private function writeEmptySheet(Worksheet $worksheet, int $month, int $year): void
    {
        $worksheet->setTitle(self::EMPTY_SHEET_TITLE);
        $worksheet->setCellValue('A1', 'Nessuna attività trovata per '.$this->formatter->monthYearLabel($month, $year));
        $worksheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->setName('Arial');
        $worksheet->getColumnDimension('A')->setWidth(60);
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function populateUserSheet(Worksheet $worksheet, Collection $entries, string $userName, int $month, int $year): void
    {
        $monthYearLabel = $this->formatter->monthYearLabel($month, $year);

        $worksheet->mergeCells('A1:H1');
        $worksheet->setCellValue('A1', "Report mensile segnatempo - {$monthYearLabel}");
        TimeEntryExportStyles::applyTitle($worksheet, 'A1:H1');
        $worksheet->getRowDimension(1)->setRowHeight(TimeEntryExportStyles::TITLE_ROW_HEIGHT);

        $worksheet->mergeCells('A2:H2');
        $worksheet->setCellValue('A2', "Collaboratore: {$userName}");
        TimeEntryExportStyles::applySubtitle($worksheet, 'A2:H2');
        $worksheet->getRowDimension(2)->setRowHeight(TimeEntryExportStyles::SUBTITLE_ROW_HEIGHT);

        $headerRow = 4;
        foreach (self::HEADERS as $index => $label) {
            $worksheet->setCellValue([$index + 1, $headerRow], $label);
        }
        TimeEntryExportStyles::applyTableHeader($worksheet, "A{$headerRow}:H{$headerRow}");
        $worksheet->getRowDimension($headerRow)->setRowHeight(TimeEntryExportStyles::HEADER_ROW_HEIGHT);

        [$lastDataRow, $firstDataRow, $totalMinutes] = $this->writeEntryRows($worksheet, $entries, $headerRow + 1);

        $totalRow = $lastDataRow + 1;
        $worksheet->mergeCells("A{$totalRow}:F{$totalRow}");
        $worksheet->setCellValue("A{$totalRow}", "TOTALE ORE {$monthYearLabel}");
        $worksheet->setCellValue("G{$totalRow}", $this->formatter->minutesToHourMinuteLabel($totalMinutes));
        TimeEntryExportStyles::applyTotalRow($worksheet, "A{$totalRow}:H{$totalRow}");
        $worksheet->getRowDimension($totalRow)->setRowHeight(TimeEntryExportStyles::TOTAL_ROW_HEIGHT);

        $this->applyColumnWidths($worksheet);
        $worksheet->freezePane("A{$firstDataRow}");

        if ($lastDataRow >= $firstDataRow) {
            $worksheet->setAutoFilter("A{$headerRow}:H{$lastDataRow}");
        }
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return array{0: int, 1: int, 2: int} [lastDataRow, firstDataRow, totalMinutes]
     */
    private function writeEntryRows(Worksheet $worksheet, Collection $entries, int $firstDataRow): array
    {
        $row = $firstDataRow;
        $totalMinutes = 0;

        foreach ($entries->values() as $index => $entry) {
            $worksheet->setCellValue([1, $row], $entry->date->format('d/m/Y'));
            $worksheet->setCellValue([2, $row], $entry->title);
            $worksheet->setCellValue([3, $row], $entry->taskType->name);
            $worksheet->setCellValue([4, $row], $this->formatter->textOrPlaceholder($entry->registry?->name));
            $worksheet->setCellValue([5, $row], $this->formatter->timeLabel($entry->start_time));
            $worksheet->setCellValue([6, $row], $this->formatter->timeLabel($entry->end_time));
            $worksheet->setCellValue([7, $row], $entry->minutes);
            $worksheet->setCellValue([8, $row], $this->formatter->textOrPlaceholder($entry->notes));

            TimeEntryExportStyles::applyBodyRow($worksheet, "A{$row}:H{$row}", $index % 2 === 0);

            $totalMinutes += $entry->minutes;
            $row++;
        }

        return [$row - 1, $firstDataRow, $totalMinutes];
    }

    private function applyColumnWidths(Worksheet $worksheet): void
    {
        foreach (self::COLUMN_WIDTHS as $column => $width) {
            $worksheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * @param  list<string>  $usedSheetNames
     */
    private function uniqueSheetName(string $userName, array &$usedSheetNames): string
    {
        $base = substr((string) preg_replace(self::INVALID_SHEET_NAME_CHARS, '', $userName), 0, self::SHEET_NAME_MAX_LENGTH);
        $candidate = $base;
        $suffix = 2;

        while (in_array($candidate, $usedSheetNames, true)) {
            $suffixText = " ({$suffix})";
            $candidate = substr($base, 0, self::SHEET_NAME_MAX_LENGTH - strlen($suffixText)).$suffixText;
            $suffix++;
        }

        $usedSheetNames[] = $candidate;

        return $candidate;
    }
}
