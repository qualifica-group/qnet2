<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Enums\ExportFormat;
use App\Enums\RequestManagementReportRowMode;
use App\Exports\ExportWriterFactory;
use App\Models\User;

/**
 * Top-level orchestrator of the report's file generation (spec 0106,
 * MT-01..MT-03; rev-2 D-11/D-13). `rows()` (spec 0107 D-2-bis, point 1) is
 * the reusable core — resolves the six branches ONCE, keeps only the ones in
 * $categoryKeys (AC-032 — a FILTER on which already-computed branches/rows
 * are written, never a different calculation) and builds every one of their
 * ReportRow — while `generate()` is `rows()` plus the file-specific part:
 * streaming the result through the ExportWriter the requested $format
 * resolves to (spec 0014's own registry, reused verbatim per D-8). The rows
 * are IDENTICAL whatever the format: only the writer changes (user directive
 * 2026-09-08, csv or xlsx).
 *
 * Invoked by GenerateRequestManagementReportJob, which has already frozen
 * the actor (Auth::setUser) and the locale (App::setLocale) before calling
 * this.
 *
 * $operators (spec 0108) is LAST and optional on both entry points, and a
 * null normalises to ReportOperatorFilter::all(): that is what makes an
 * ExportRun frozen before spec 0108 — its state has no `operator_keys` —
 * generate exactly the file it always did (D-2, AC-014).
 */
final class RequestManagementReportGenerator
{
    public function __construct(
        private readonly ReportBranchResolver $branches,
        private readonly ReportBranchRowsBuilder $rowsBuilder,
        private readonly ReportSheetBuilder $sheetBuilder,
        private readonly ExportWriterFactory $writers,
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
        ExportFormat $format,
        string $absolutePath,
        ?ReportOperatorFilter $operators = null,
    ): int {
        // Step 1: every selected branch's already-computed rows — the reusable core.
        $branchRows = $this->rows($actor, $dateFrom, $dateTo, $categoryKeys, $rowMode, $operators);

        // Step 2: open the format's own writer, translated header row first.
        $writer = $this->writers->make($format);
        $writer->open($absolutePath);
        $writer->writeHeaders($this->sheetBuilder->headers());

        // Step 3: one selected branch at a time — TOTALE/GA2/"Non assegnato" rows, as $rowMode allowed.
        $rowCount = 0;

        foreach ($branchRows as $branchRow) {
            foreach ($branchRow['rows'] as $row) {
                $writer->writeRow($this->sheetBuilder->row($branchRow['branch']->label, $row));
                $rowCount++;
            }
        }

        $writer->close();

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
        ?ReportOperatorFilter $operators = null,
    ): array {
        $branches = $this->selectedBranches($categoryKeys);
        $range = ReportDateRange::fromRequest($dateFrom, $dateTo);
        $operators ??= ReportOperatorFilter::all();

        return array_map(
            fn (ReportBranch $branch): array => [
                'branch' => $branch,
                'rows' => $this->rowsBuilder->build($branch, $actor, $range, $rowMode, $operators),
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
