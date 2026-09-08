<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Dashboard;

use App\Enums\RequestManagementDashboardChartScope;
use App\Enums\RequestManagementReportRowMode;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportBranch;
use App\Services\RequestManagement\Report\ReportBranchRowsBuilder;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\ReportRow;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;

/**
 * Assembles the dashboard's `summary` + `charts` (spec 0107, D-2 — the
 * central constraint): every number comes from `ReportRow::$values`,
 * produced by the SAME `ReportBranchRowsBuilder::build()` the CSV uses
 * (spec 0106). This class builds NO query, checks NO workflow status, reads
 * NO date column — it only selects, sorts and reshapes already-computed
 * rows. If a rule ever needs to change here, it needs to change in
 * `Indicators/*` instead — this class has nowhere for it to live.
 *
 * Branch selection is NOT reimplemented here (spec 0107 D-2-bis, point 1):
 * `RequestManagementReportGenerator::rows()` is the ONE place that filters
 * the six branches by `category_keys`, reused verbatim — the CSV and this
 * dashboard can never see a different set of branches for the same filters.
 *
 * The same holds for the operator selection (spec 0108, D-1): $operators is
 * handed down UNTOUCHED to all three calls below — the two `rows()` and the
 * synthetic summary branch — so the tiles, the per-category charts and the
 * per-operator charts are all computed on the same narrowed perimeter, and
 * none of them can end up describing a different set of operators than the
 * CSV generated from the very same filters.
 */
final class RequestManagementDashboardBuilder
{
    public function __construct(
        private readonly RequestManagementReportGenerator $generator,
        private readonly ReportBranchRowsBuilder $rowsBuilder,
    ) {}

    /**
     * @param  array<int, string>  $categoryKeys
     */
    public function build(
        ?User $actor,
        string $dateFrom,
        string $dateTo,
        array $categoryKeys,
        RequestManagementReportRowMode $rowMode,
        ?ReportOperatorFilter $operators = null,
    ): RequestManagementDashboardResult {
        $operators ??= ReportOperatorFilter::all();

        // Step 1: every selected branch's own TOTALE row — also the source
        // of the selected-branch list itself (summary needs it regardless
        // of $rowMode, D-3).
        $totalsByBranch = $this->generator->rows($actor, $dateFrom, $dateTo, $categoryKeys, RequestManagementReportRowMode::TotalOnly, $operators);
        $branches = array_map(static fn (array $pair): ReportBranch => $pair['branch'], $totalsByBranch);
        $range = ReportDateRange::fromRequest($dateFrom, $dateTo);

        // Step 2: the summary cards — the SAME builder, on a synthetic
        // union branch (D-8), never a sum of the per-category totals.
        $summary = $this->buildSummary($branches, $actor, $range, $operators);

        // Step 3: charts — which scopes appear is decided by $rowMode (D-3).
        $charts = [];

        if ($rowMode !== RequestManagementReportRowMode::OperatorsOnly) {
            $charts = [...$charts, ...$this->buildCategoryCharts($totalsByBranch)];
        }

        if ($rowMode !== RequestManagementReportRowMode::TotalOnly) {
            $operatorsByBranch = $this->generator->rows($actor, $dateFrom, $dateTo, $categoryKeys, RequestManagementReportRowMode::OperatorsOnly, $operators);
            $charts = [...$charts, ...$this->buildOperatorCharts($operatorsByBranch)];
        }

        return new RequestManagementDashboardResult($summary, $charts);
    }

    /**
     * D-8: categoryIds = union of the selected branches' own (already
     * subtree-expanded) ids; columns = union of their applicability lists.
     * Feeding this into ReportBranchRowsBuilder::build() is the SAME
     * `count(distinct quotes.id)` a real branch gets — a synthetic branch
     * is not a shortcut, it is the mechanism D-8 specifies.
     *
     * @param  array<int, ReportBranch>  $branches
     * @return array<int, DashboardSummaryItem>
     */
    private function buildSummary(array $branches, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators): array
    {
        $synthetic = new ReportBranch(
            key: '__summary__',
            label: '',
            categoryIds: $this->unionOf($branches, static fn (ReportBranch $b): array => $b->categoryIds),
            columns: $this->orderedIndicatorKeys($branches),
        );

        /** @var ReportRow $total */
        [$total] = $this->rowsBuilder->build($synthetic, $actor, $range, RequestManagementReportRowMode::TotalOnly, $operators);

        return array_map(
            static fn (string $key): DashboardSummaryItem => new DashboardSummaryItem(
                $key,
                __("request-management-report.headers.{$key}"),
                $total->values[$key],
            ),
            $synthetic->columns,
        );
    }

    /**
     * One chart per indicator applicable to at least one selected branch:
     * one point per selected category, its OWN already-computed TOTALE row.
     *
     * @param  array<int, array{branch: ReportBranch, rows: array<int, ReportRow>}>  $totalsByBranch
     * @return array<int, DashboardChart>
     */
    private function buildCategoryCharts(array $totalsByBranch): array
    {
        $branches = array_map(static fn (array $pair): ReportBranch => $pair['branch'], $totalsByBranch);

        $totalRowByBranchKey = [];
        foreach ($totalsByBranch as $pair) {
            [$total] = $pair['rows']; // total_only always emits exactly one row.
            $totalRowByBranchKey[$pair['branch']->key] = $total;
        }

        $charts = [];

        foreach ($this->orderedIndicatorKeys($branches) as $indicatorKey) {
            $points = $this->sorted(array_map(
                static fn (ReportBranch $branch): DashboardPoint => new DashboardPoint(
                    $branch->label,
                    $totalRowByBranchKey[$branch->key]->values[$indicatorKey],
                ),
                $branches,
            ));

            if ($this->allZero($points)) {
                continue; // AC-008: no noise from an indicator nobody has.
            }

            $charts[] = new DashboardChart(
                id: "category-{$indicatorKey}",
                scope: RequestManagementDashboardChartScope::Category,
                categoryKey: null,
                categoryLabel: null,
                indicatorKey: $indicatorKey,
                indicatorLabel: __("request-management-report.headers.{$indicatorKey}"),
                points: $points,
            );
        }

        return $charts;
    }

    /**
     * One chart per (selected category, applicable indicator): one point
     * per GA2 of that branch's OWN already-computed operator rows
     * (including "Non assegnato", itself a GA2 row — spec 0106 D-13).
     *
     * @param  array<int, array{branch: ReportBranch, rows: array<int, ReportRow>}>  $operatorsByBranch
     * @return array<int, DashboardChart>
     */
    private function buildOperatorCharts(array $operatorsByBranch): array
    {
        $charts = [];

        foreach ($operatorsByBranch as $pair) {
            $branch = $pair['branch'];

            foreach ($this->orderedIndicatorKeys([$branch]) as $indicatorKey) {
                $points = $this->sorted(array_map(
                    static fn (ReportRow $row): DashboardPoint => new DashboardPoint($row->label, $row->values[$indicatorKey]),
                    $pair['rows'],
                ));

                if ($points === [] || $this->allZero($points)) {
                    continue; // no GA2 at all, or every one of them is 0 (AC-008).
                }

                $charts[] = new DashboardChart(
                    id: "operator-{$branch->key}-{$indicatorKey}",
                    scope: RequestManagementDashboardChartScope::Operator,
                    categoryKey: $branch->key,
                    categoryLabel: $branch->label,
                    indicatorKey: $indicatorKey,
                    indicatorLabel: __("request-management-report.headers.{$indicatorKey}"),
                    points: $points,
                );
            }
        }

        return $charts;
    }

    /**
     * AC-007: value descending, label ascending on ties — deterministic
     * regardless of the (name-sorted) order ReportBranchRowsBuilder itself
     * returns rows in.
     *
     * @param  array<int, DashboardPoint>  $points
     * @return array<int, DashboardPoint>
     */
    private function sorted(array $points): array
    {
        usort($points, static fn (DashboardPoint $a, DashboardPoint $b): int => $b->value <=> $a->value ?: $a->label <=> $b->label);

        return $points;
    }

    /**
     * @param  array<int, DashboardPoint>  $points
     */
    private function allZero(array $points): bool
    {
        foreach ($points as $point) {
            if ($point->value !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * The union of $branches' `columns`, reordered to the report's
     * canonical indicator order — `config('request-management-report.
     * indicator_columns')`, the SAME neutral source ReportSheetBuilder reads,
     * never a new order of its own (spec 0107 D-2-bis, point 3).
     *
     * @param  array<int, ReportBranch>  $branches
     * @return array<int, string>
     */
    private function orderedIndicatorKeys(array $branches): array
    {
        return array_values(array_intersect(
            (array) config('request-management-report.indicator_columns'),
            $this->unionOf($branches, static fn (ReportBranch $b): array => $b->columns),
        ));
    }

    /**
     * @param  array<int, ReportBranch>  $branches
     * @param  callable(ReportBranch): array<int, mixed>  $pluck
     * @return array<int, mixed>
     */
    private function unionOf(array $branches, callable $pluck): array
    {
        return array_values(array_unique(array_merge([], ...array_map($pluck, $branches))));
    }
}
