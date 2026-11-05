<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\Exports\ChartOfAccounts\ChartOfAccountsLayout;
use App\Imports\Support\ColumnAnalysis;
use App\Imports\Support\SpreadsheetReader;
use App\Imports\Support\SpreadsheetReaderException;
use Illuminate\Validation\ValidationException;

/**
 * Reads the import file into rows keyed by layout column (spec 0349, D-3, D-11).
 * Headers are recognized in either language; a file that cannot be read, lacks
 * a column or exceeds the row limit is refused as a whole (422 on `file`).
 */
final class ChartOfAccountsFileReader
{
    public const int MAX_ROWS = 2000;

    public function __construct(private readonly SpreadsheetReader $spreadsheet) {}

    /**
     * @return list<ImportRow>
     */
    public function read(string $path): array
    {
        try {
            // Step 1: header and size
            $analysis = $this->spreadsheet->analyze($path);
            $columnByKey = $this->mapColumns($analysis);

            if ($analysis->rowCount > self::MAX_ROWS) {
                $this->refuse('too_many_rows', ['max' => self::MAX_ROWS]);
            }

            // Step 2: data rows. The reader skips blank lines, so row numbers
            // follow the non-blank rows (header = 1).
            $rows = [];
            foreach ($this->spreadsheet->rows($path) as $number => $values) {
                $cells = [];
                foreach ($columnByKey as $key => $column) {
                    $cells[$column] = $values[$key] ?? '';
                }
                $rows[] = new ImportRow($number + 1, $cells);
            }

            return $rows;
        } catch (SpreadsheetReaderException) {
            $this->refuse('unreadable_file');
        }
    }

    /**
     * Reader column key => layout column; the first of two same-named headers
     * wins. Throws when a layout column is missing.
     *
     * @return array<string, string>
     */
    private function mapColumns(ColumnAnalysis $analysis): array
    {
        $lookup = ChartOfAccountsLayout::headerLookup();
        $map = [];

        foreach (ColumnAnalysis::columnKeys($analysis->columns) as $index => $key) {
            $column = $lookup[ChartOfAccountsLayout::normalize($analysis->columns[$index]['name'])] ?? null;

            if ($column !== null && ! in_array($column, $map, true)) {
                $map[$key] = $column;
            }
        }

        $missing = array_diff(ChartOfAccountsLayout::COLUMNS, $map);
        if ($missing !== []) {
            $this->refuse('missing_columns', ['columns' => implode(', ', array_map(ChartOfAccountsLayout::header(...), $missing))]);
        }

        return $map;
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    private function refuse(string $messageKey, array $replace = []): never
    {
        throw ValidationException::withMessages(['file' => [__("chart_accounts.io.errors.{$messageKey}", $replace)]]);
    }
}
