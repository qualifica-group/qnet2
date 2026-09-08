<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Enums\RequestManagementReportRowMode;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Assembles one branch's rows (spec 0106): TOTAL first (unless $rowMode
 * excludes it), then a row per named GA2 sorted by `users.name` (AC-006),
 * then "Non assegnato" last if any request in scope has no operator
 * (AC-007) — "Non assegnato" IS a GA2 row (D-13), so it follows the same
 * $rowMode gate as the named operators.
 *
 * $rowMode (spec 0106 rev-2, D-13) only decides WHICH already-computed rows
 * are emitted — it never changes a value (AC-032): `total_only` keeps just
 * the TOTALE row, `operators_only` drops TOTALE (and, naturally, returns NO
 * rows at all for a branch with neither a named operator nor an unassigned
 * request — AC-034), `all` keeps every row.
 *
 * Every column of `config('request-management-report.indicator_columns')`
 * is always present in the emitted values, defaulting to 0 (D-15, rev-2 —
 * overrides the former D-9 empty-cell distinction) whether the column is a
 * stub, not applicable to this branch, or simply not computed for this row.
 * That config key is the neutral source both this (calculation) class and
 * ReportCsvBuilder (formatting) read from — this class never depends on the
 * formatting one for it (spec 0107 D-2-bis, point 3).
 */
final class ReportBranchRowsBuilder
{
    public function __construct(private readonly ReportIndicatorRegistry $indicators) {}

    /**
     * @return array<int, ReportRow>
     */
    public function build(ReportBranch $branch, ?User $actor, ReportDateRange $range, RequestManagementReportRowMode $rowMode): array
    {
        // Step 1: compute every REAL (non-stub) applicable indicator once for the whole branch.
        $results = $this->computeIndicators($branch, $actor, $range);

        // Step 2: the GA2 breakdown to emit — union of operator ids across every computed indicator.
        $operatorIds = $this->operatorIds($results);
        $namedOperators = $this->namedOperatorsSorted($operatorIds);
        $hasUnassigned = in_array(null, $operatorIds, true);

        // Step 3: TOTALE row, then one row per named operator, then "Non assegnato" last — each gated by $rowMode.
        $rows = [];

        if ($rowMode !== RequestManagementReportRowMode::OperatorsOnly) {
            $rows[] = $this->row(__('request-management-report.labels.total'), null, isTotal: true, results: $results);
        }

        if ($rowMode !== RequestManagementReportRowMode::TotalOnly) {
            foreach ($namedOperators as $operator) {
                $rows[] = $this->row($operator->name, $operator->id, isTotal: false, results: $results);
            }

            if ($hasUnassigned) {
                $rows[] = $this->row(__('request-management-report.labels.unassigned'), null, isTotal: false, results: $results);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, IndicatorResult>
     */
    private function computeIndicators(ReportBranch $branch, ?User $actor, ReportDateRange $range): array
    {
        $results = [];

        foreach ($branch->columns as $column) {
            $indicator = $this->indicators->resolve($column);

            if ($indicator !== null) {
                $results[$column] = $indicator->compute($branch->categoryIds, $actor, $range);
            }
        }

        return $results;
    }

    /**
     * @param  array<string, IndicatorResult>  $results
     * @return array<int, int|null>
     */
    private function operatorIds(array $results): array
    {
        $ids = [];

        foreach ($results as $result) {
            $ids = array_merge($ids, $result->operatorIds());
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, int|null>  $operatorIds
     * @return Collection<int, User>
     */
    private function namedOperatorsSorted(array $operatorIds): Collection
    {
        $ids = array_values(array_filter($operatorIds, static fn (?int $id): bool => $id !== null));

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @param  array<string, IndicatorResult>  $results
     */
    private function row(string $label, ?int $operatorId, bool $isTotal, array $results): ReportRow
    {
        $values = [];

        foreach ((array) config('request-management-report.indicator_columns') as $column) {
            if (! isset($results[$column])) {
                $values[$column] = 0; // stub, or not applicable to this branch (D-15)

                continue;
            }

            $values[$column] = $isTotal ? $results[$column]->total : $results[$column]->valueFor($operatorId);
        }

        return new ReportRow($label, $values);
    }
}
