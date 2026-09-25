<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Enums\ExportFormat;
use App\Enums\RequestManagementReportRowMode;
use App\Exports\ExportWriterFactory;
use App\Models\User;
use App\RequestManagement\RequestModule;

/**
 * Top-level orchestrator of the report's file generation (spec 0106,
 * MT-01..MT-03; rev-2 D-11/D-13). `rows()` (spec 0107 D-2-bis, point 1) is
 * the reusable core — resolves the reportable branches ONCE, keeps only the ones in
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
 * $operators (spec 0108) and $sites (spec 0112) are the LAST, optional
 * parameters of both entry points, and a null normalises to the filter's own
 * `all()`: that is what makes an ExportRun frozen before either spec — its
 * state has no `operator_keys`, no `site_keys` — generate exactly the file it
 * always did (0108 D-2/AC-014, 0112 D-4/AC-013).
 *
 * Spec 0130: $module is the LAST, defaulted (RequestModule::Requests)
 * parameter of both entry points too — GenerateRequestManagementReportJob
 * reads it off the run's own frozen state (absent = Requests, same
 * optional-key convention as $operators/$sites above) and hands it straight
 * through to rows()/generate(), which pass it on to ReportBranchRowsBuilder
 * unchanged.
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
        ?string $dateFrom,
        ?string $dateTo,
        array $categoryKeys,
        RequestManagementReportRowMode $rowMode,
        ExportFormat $format,
        string $absolutePath,
        ?ReportOperatorFilter $operators = null,
        ?ReportSiteFilter $sites = null,
        RequestModule $module = RequestModule::Requests,
    ): int {
        // Step 1: every selected branch's already-computed rows — the reusable core.
        $branchRows = $this->rows($actor, $dateFrom, $dateTo, $categoryKeys, $rowMode, $operators, $sites, $module);

        // Step 2: this export's own column set (spec 0141 D-4) — the union,
        // in catalog order, of every selected branch's configured columns —
        // then open the format's own writer, translated header row first.
        $columns = $this->exportColumns($branchRows);
        $writer = $this->writers->make($format);
        $writer->open($absolutePath);
        $writer->writeHeaders($this->sheetBuilder->headers($columns));

        // Step 3: one selected branch at a time — TOTALE/GA2/"Non assegnato" rows, as $rowMode allowed.
        $rowCount = 0;

        foreach ($branchRows as $branchRow) {
            foreach ($branchRow['rows'] as $row) {
                $writer->writeRow($this->sheetBuilder->row($branchRow['branch']->label, $row, $columns));
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
        ?string $dateFrom,
        ?string $dateTo,
        array $categoryKeys,
        RequestManagementReportRowMode $rowMode,
        ?ReportOperatorFilter $operators = null,
        ?ReportSiteFilter $sites = null,
        RequestModule $module = RequestModule::Requests,
    ): array {
        $branches = $this->selectedBranches($categoryKeys);
        $range = ReportDateRange::fromRequest($dateFrom, $dateTo);
        $operators ??= ReportOperatorFilter::all();
        $sites ??= ReportSiteFilter::all();

        return array_map(
            fn (ReportBranch $branch): array => [
                'branch' => $branch,
                'rows' => $this->rowsBuilder->build($branch, $actor, $range, $rowMode, $operators, $sites, $module),
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

    /**
     * Spec 0141 D-4: the union, in catalog order, of every branch's own
     * configured columns — never the full catalog. A column absent from
     * every selected branch's configuration is dropped from the header
     * entirely, not merely zeroed.
     *
     * @param  array<int, array{branch: ReportBranch, rows: array<int, ReportRow>}>  $branchRows
     * @return array<int, string>
     */
    private function exportColumns(array $branchRows): array
    {
        $columns = [];

        foreach ((array) config('request-management-report.indicator_columns') as $column) {
            foreach ($branchRows as $branchRow) {
                if ($branchRow['branch']->categoryIdsFor($column) !== null) {
                    $columns[] = $column;

                    continue 2;
                }
            }
        }

        return $columns;
    }
}
