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
use App\Services\RequestManagement\Report\ReportSiteFilter;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;

/**
 * Assembles the dashboard's overall `summary` + per-category sections (spec
 * 0107, D-2 — the central constraint): every number comes from
 * `ReportRow::$values`, produced by the SAME `ReportBranchRowsBuilder::build()`
 * the CSV uses (spec 0106). This class builds NO query, checks NO workflow
 * status, reads NO date column — it only selects, sorts and reshapes
 * already-computed rows. If a rule ever needs to change here, it needs to
 * change in `Indicators/*` instead — this class has nowhere for it to live.
 *
 * Branch selection is NOT reimplemented here (spec 0107 D-2-bis, point 1):
 * `RequestManagementReportGenerator::rows()` is the ONE place that filters
 * the six branches by `category_keys`, reused verbatim — the CSV and this
 * dashboard can never see a different set of branches for the same filters.
 *
 * The same holds for the operator selection (spec 0108, D-1) and for the Sede
 * selection (spec 0112, D-3): $operators and $sites are handed down UNTOUCHED
 * to all three calls below — the two `rows()` and the synthetic summary branch
 * — so the tiles, the section tiles and the per-operator charts are all
 * computed on the same narrowed perimeter, and none of them can end up
 * describing a different set of requests than the CSV generated from the very
 * same filters.
 *
 * Rev-3 (user directive 2026-09-08) changed only the SHAPE, never a value:
 * every indicator column is emitted even when it is 0 (D-11), and the charts
 * hang off their own category (D-10) instead of comparing categories.
 * WHAT is computed is untouched — a column outside `ReportBranch::$columns`
 * is still never queried, it simply renders as the 0 the row already carries
 * (spec 0106 D-15), which is exactly what the CSV prints for it.
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
        ?ReportSiteFilter $sites = null,
    ): RequestManagementDashboardResult {
        $operators ??= ReportOperatorFilter::all();
        $sites ??= ReportSiteFilter::all();

        // Step 1: every selected branch's own TOTALE row — also the source
        // of the selected-branch list itself (the overall tiles need it
        // regardless of $rowMode, D-3).
        $totalsByBranch = $this->generator->rows($actor, $dateFrom, $dateTo, $categoryKeys, RequestManagementReportRowMode::TotalOnly, $operators, $sites);
        $branches = array_map(static fn (array $pair): ReportBranch => $pair['branch'], $totalsByBranch);
        $range = ReportDateRange::fromRequest($dateFrom, $dateTo);

        // Step 2: the overall tiles — the SAME builder, on a synthetic union
        // branch (D-8), never a sum of the per-category totals.
        $summary = $this->buildOverallSummary($branches, $actor, $range, $operators, $sites);

        // Step 3: the GA2 rows of each branch, fetched only when $rowMode
        // actually asks for an operator breakdown (D-3).
        $operatorRows = $rowMode === RequestManagementReportRowMode::TotalOnly
            ? []
            : $this->operatorRowsByBranchKey($actor, $dateFrom, $dateTo, $categoryKeys, $operators, $sites);

        // Step 4: one section per selected category, in the report's own
        // branch order (D-10).
        $categories = array_map(
            fn (array $pair): DashboardCategory => $this->buildCategory($pair['branch'], $pair['rows'][0], $operatorRows, $rowMode),
            $totalsByBranch,
        );

        return new RequestManagementDashboardResult($summary, $categories);
    }

    /**
     * D-8: categoryIds = union of the selected branches' own (already
     * subtree-expanded) ids; columns = union of their applicability lists, so
     * nothing gets QUERIED that a real branch would not query. What is
     * EMITTED is the full column list either way (D-11): a column outside the
     * union is the 0 the row already holds.
     *
     * @param  array<int, ReportBranch>  $branches
     * @return array<int, DashboardSummaryItem>
     */
    private function buildOverallSummary(array $branches, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ReportSiteFilter $sites): array
    {
        $synthetic = new ReportBranch(
            key: '__summary__',
            label: '',
            categoryIds: $this->unionOf($branches, static fn (ReportBranch $b): array => $b->categoryIds),
            columns: $this->unionOf($branches, static fn (ReportBranch $b): array => $b->columns),
        );

        /** @var ReportRow $total */
        [$total] = $this->rowsBuilder->build($synthetic, $actor, $range, RequestManagementReportRowMode::TotalOnly, $operators, $sites);

        return $this->summaryOf($total);
    }

    /**
     * One section: the branch's own TOTALE row as tiles, plus its charts.
     *
     * @param  array<string, array<int, ReportRow>>  $operatorRows
     */
    private function buildCategory(ReportBranch $branch, ReportRow $total, array $operatorRows, RequestManagementReportRowMode $rowMode): DashboardCategory
    {
        return new DashboardCategory(
            key: $branch->key,
            label: $branch->label,
            summary: $this->summaryOf($total),
            charts: $this->chartsOf($branch, $total, $operatorRows[$branch->key] ?? [], $rowMode),
        );
    }

    /**
     * @param  array<int, ReportRow>  $operatorRows
     * @return array<int, DashboardChart>
     */
    private function chartsOf(ReportBranch $branch, ReportRow $total, array $operatorRows, RequestManagementReportRowMode $rowMode): array
    {
        $charts = [];

        if ($rowMode !== RequestManagementReportRowMode::OperatorsOnly) {
            $charts[] = $this->indicatorChart($branch, $total);
        }

        // A branch with neither a named GA2 nor an unassigned request has no
        // operator rows at all: a chart with no bar cannot be drawn, and is
        // the ONE case rev-3 still leaves out.
        if ($rowMode !== RequestManagementReportRowMode::TotalOnly && $operatorRows !== []) {
            foreach ($this->indicatorKeys() as $indicatorKey) {
                $charts[] = $this->operatorChart($branch, $operatorRows, $indicatorKey);
            }
        }

        return $charts;
    }

    /**
     * The category's own indicators side by side, in the report's canonical
     * column order — NOT sorted by value: two sections must stay comparable
     * bar by bar, and the order is deterministic either way (AC-007).
     */
    private function indicatorChart(ReportBranch $branch, ReportRow $total): DashboardChart
    {
        return new DashboardChart(
            id: "indicator-{$branch->key}",
            scope: RequestManagementDashboardChartScope::Indicator,
            indicatorKey: null,
            indicatorLabel: null,
            points: array_map(
                fn (string $key): DashboardPoint => new DashboardPoint($this->indicatorLabel($key), $total->values[$key]),
                $this->indicatorKeys(),
            ),
        );
    }

    /**
     * One indicator of this category, broken down by GA2 (including "Non
     * assegnato", itself a GA2 row — spec 0106 D-13).
     *
     * @param  array<int, ReportRow>  $operatorRows
     */
    private function operatorChart(ReportBranch $branch, array $operatorRows, string $indicatorKey): DashboardChart
    {
        return new DashboardChart(
            id: "operator-{$branch->key}-{$indicatorKey}",
            scope: RequestManagementDashboardChartScope::Operator,
            indicatorKey: $indicatorKey,
            indicatorLabel: $this->indicatorLabel($indicatorKey),
            points: $this->sorted(array_map(
                static fn (ReportRow $row): DashboardPoint => new DashboardPoint($row->label, $row->values[$indicatorKey]),
                $operatorRows,
            )),
        );
    }

    /**
     * @param  array<int, string>  $categoryKeys
     * @return array<string, array<int, ReportRow>>
     */
    private function operatorRowsByBranchKey(?User $actor, string $dateFrom, string $dateTo, array $categoryKeys, ReportOperatorFilter $operators, ReportSiteFilter $sites): array
    {
        $rowsByKey = [];

        foreach ($this->generator->rows($actor, $dateFrom, $dateTo, $categoryKeys, RequestManagementReportRowMode::OperatorsOnly, $operators, $sites) as $pair) {
            $rowsByKey[$pair['branch']->key] = $pair['rows'];
        }

        return $rowsByKey;
    }

    /**
     * D-11: EVERY indicator column becomes a tile, zeros included — the same
     * eleven cells the CSV prints for the same row.
     *
     * @return array<int, DashboardSummaryItem>
     */
    private function summaryOf(ReportRow $row): array
    {
        return array_map(
            fn (string $key): DashboardSummaryItem => new DashboardSummaryItem($key, $this->indicatorLabel($key), $row->values[$key]),
            $this->indicatorKeys(),
        );
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
     * The report's canonical indicator order — `config('request-management-
     * report.indicator_columns')`, the SAME neutral source ReportSheetBuilder
     * reads, never an order of its own (spec 0107 D-2-bis, point 3). Every
     * key is present in every `ReportRow` (spec 0106 D-15), so no
     * applicability intersection is needed to read one.
     *
     * @return array<int, string>
     */
    private function indicatorKeys(): array
    {
        return (array) config('request-management-report.indicator_columns');
    }

    /** The CSV's own header label (D-9): titles and headings cannot diverge. */
    private function indicatorLabel(string $key): string
    {
        return __("request-management-report.headers.{$key}");
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
