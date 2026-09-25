<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

/**
 * Translates a ReportBranch/ReportRow pair into the CSV's variable-width
 * shape (spec 0106 data_contract, spec 0141 D-4): the header row (translated
 * in the job's frozen locale) and each data row, both keyed on the SAME
 * `$columns` list the caller resolves once per export — the union, in
 * catalog order, of every selected branch's own configured columns
 * (RequestManagementReportGenerator). A cell of a column not configured for
 * that particular row's branch renders empty (D-3); every other numeric cell
 * renders as-is, 0 included. `Categoria`/`GA2` stay the only text cells.
 *
 * The eleven indicator columns are NOT owned here (spec 0107 D-2-bis, point
 * 3): they live in `config('request-management-report.indicator_columns')`,
 * the same neutral source ReportBranchRowsBuilder reads — this class is
 * just one more reader, never the source of truth other layers depend on.
 */
final class ReportSheetBuilder
{
    /**
     * @param  array<int, string>  $columns  the export's own resolved column set (catalog order)
     * @return array<int, string>
     */
    public function headers(array $columns): array
    {
        return array_map(
            static fn (string $key): string => __("request-management-report.headers.{$key}"),
            $this->columnOrder($columns),
        );
    }

    /**
     * @param  array<int, string>  $columns  the export's own resolved column set (catalog order)
     * @return array<int, string>
     */
    public function row(string $categoryLabel, ReportRow $row, array $columns): array
    {
        $cells = [];

        foreach ($this->columnOrder($columns) as $key) {
            $cells[] = match ($key) {
                'category' => $categoryLabel,
                'ga2' => $row->label,
                default => $row->values[$key] === null ? '' : (string) $row->values[$key], // D-3: not configured = empty
            };
        }

        return $cells;
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function columnOrder(array $columns): array
    {
        return ['category', 'ga2', ...$columns];
    }
}
