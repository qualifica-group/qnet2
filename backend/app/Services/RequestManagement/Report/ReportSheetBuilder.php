<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

/**
 * Translates a ReportBranch/ReportRow pair into the CSV's fixed 13-column
 * shape (spec 0106 data_contract): the header row (translated in the job's
 * frozen locale) and each data row. D-15 (rev-2, overrides D-9): EVERY
 * numeric cell renders as `0`, never empty and never `null` — a column not
 * applicable to the branch and a stub not yet implemented are, since D-15,
 * indistinguishable on purpose. `Categoria`/`GA2` stay the only text cells.
 *
 * The eleven indicator columns are NOT owned here (spec 0107 D-2-bis, point
 * 3): they live in `config('request-management-report.indicator_columns')`,
 * the same neutral source ReportBranchRowsBuilder reads — this class is
 * just one more reader, never the source of truth other layers depend on.
 */
final class ReportSheetBuilder
{
    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return array_map(
            static fn (string $key): string => __("request-management-report.headers.{$key}"),
            $this->columnOrder(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function row(string $categoryLabel, ReportRow $row): array
    {
        $cells = [];

        foreach ($this->columnOrder() as $key) {
            $cells[] = match ($key) {
                'category' => $categoryLabel,
                'ga2' => $row->label,
                default => (string) ($row->values[$key] ?? 0), // D-15: never empty
            };
        }

        return $cells;
    }

    /**
     * The 13 columns, in the CONTRACT order.
     *
     * @return array<int, string>
     */
    private function columnOrder(): array
    {
        return ['category', 'ga2', ...(array) config('request-management-report.indicator_columns')];
    }
}
