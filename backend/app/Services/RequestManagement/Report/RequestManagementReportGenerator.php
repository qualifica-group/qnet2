<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Enums\RequestManagementReportRowMode;
use App\Exports\CsvExportWriter;
use App\Models\User;

/**
 * Top-level orchestrator of the report's CSV generation (spec 0106,
 * MT-01..MT-03; rev-2 D-11/D-13). `rows()` (spec 0107 D-2-bis, point 1) is
 * the reusable core — resolves the six branches ONCE, keeps only the ones in
 * $categoryKeys (AC-032 — a FILTER on which already-computed branches/rows
 * are written, never a different calculation) and builds every one of their
 * ReportRow — while `generate()` is `rows()` plus the CSV-specific part:
 * streaming the result through the project's own CsvExportWriter (spec
 * 0014, reused verbatim per D-8 — BOM, ',', '"', no escape).
 *
 * Invoked by GenerateRequestManagementReportJob, which has already frozen
 * the actor (Auth::setUser) and the locale (App::setLocale) before calling
 * this.
 */
final class RequestManagementReportGenerator
{
    public function __construct(
        private readonly ReportBranchResolver $branches,
        private readonly ReportBranchRowsBuilder $rowsBuilder,
        private readonly ReportCsvBuilder $csvBuilder,
        private readonly CsvExportWriter $writer,
    ) {}

    /**
     * @param  array<int, string>  $categoryKeys
     */
    public function generate(
        User $actor,
        string $dateFrom,
        string $dateTo,
        array $categoryKeys,
        RequestManagementReportRowMode $rowMode,
        string $absolutePath,
    ): int {
        // Step 1: every selected branch's already-computed rows — the reusable core.
        $branchRows = $this->rows($actor, $dateFrom, $dateTo, $categoryKeys, $rowMode);

        // Step 2: open the writer, translated header row first.
        $this->writer->open($absolutePath);
        $this->writer->writeHeaders($this->csvBuilder->headers());

        // Step 3: one selected branch at a time — TOTALE/GA2/"Non assegnato" rows, as $rowMode allowed.
        $rowCount = 0;

        foreach ($branchRows as $branchRow) {
            foreach ($branchRow['rows'] as $row) {
                $this->writer->writeRow($this->csvBuilder->row($branchRow['branch']->label, $row));
                $rowCount++;
            }
        }

        $this->writer->close();

        return $rowCount;
    }

    /**
     * The reusable core (spec 0107 D-2-bis, point 1): every branch selected
     * by $categoryKeys, paired with its own already-computed ReportRow list
     * for $rowMode. Consumed by generate() above AND by
     * RequestManagementDashboardBuilder (spec 0107) — the ONE place that
     * filters branches by $categoryKeys, so the filter can never diverge
     * between the CSV and the dashboard.
     *
     * @param  array<int, string>  $categoryKeys
     * @return array<int, array{branch: ReportBranch, rows: array<int, ReportRow>}>
     */
    public function rows(
        User $actor,
        string $dateFrom,
        string $dateTo,
        array $categoryKeys,
        RequestManagementReportRowMode $rowMode,
    ): array {
        $branches = $this->selectedBranches($categoryKeys);
        $range = ReportDateRange::fromRequest($dateFrom, $dateTo);

        return array_map(
            fn (ReportBranch $branch): array => [
                'branch' => $branch,
                'rows' => $this->rowsBuilder->build($branch, $actor, $range, $rowMode),
            ],
            $branches,
        );
    }

    /**
     * @param  array<int, string>  $categoryKeys
     * @return array<int, ReportBranch>
     */
    private function selectedBranches(array $categoryKeys): array
    {
        return array_values(array_filter(
            $this->branches->resolve(),
            static fn (ReportBranch $branch): bool => in_array($branch->key, $categoryKeys, true),
        ));
    }
}
