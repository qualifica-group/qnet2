<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Enums\WorkflowStatusGroup;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\IndicatorResult;
use App\Services\RequestManagement\Report\QuoteCountAggregator;
use App\Services\RequestManagement\Report\ReportBranchQuery;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportIndicator;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\ReportSiteFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Shared formula for "N. Potenziali associati" (D-2, `group` in
 * pending/validated) and "Associati"/"Trattative Concluse" (D-3, `group` =
 * closed_won) — one identical implementation behind two report columns, the
 * brief distinguishes them only by category label.
 *
 * transition_resolution (D-3), three steps:
 *  1. targetStatusIds() resolves every status id whose `group` is one of
 *     $groups, GLOBALLY (no workflow filter yet). A branch whose catalogue
 *     has NO status in that group (D-2: e.g. GOL/Autofinanziato/APL for
 *     'validated') naturally resolves to an empty id list, and
 *     `whereIn(..., [])` matches nothing — 0 with no special-casing.
 *  2. whereExists on activity_log, matched via the arrow operator on
 *     `properties->attributes->quote_workflow_status_id` (never
 *     whereRaw/json_extract, AC-023 — the same idiom as
 *     AttributeDateFilterApplier).
 *  3. Disambiguation: the logged target status must belong to the SAME
 *     `quote_workflow_id` as the quote's CURRENT status — compared
 *     null-safely, since two quotes both on the GLOBAL default set
 *     (`quote_workflow_id IS NULL`, e.g. a category with no dedicated
 *     workflow) are on the very same set and must still match, which a
 *     plain `=` would miss (SQL `NULL = NULL` is NULL, not TRUE).
 */
final class WorkflowTransitionIndicator implements ReportIndicator
{
    private const string LOG_NAME = 'opportunities';

    private const string SUBJECT_TYPE = 'opportunity';

    private const string LOGGED_STATUS_COLUMN = 'a.properties->attributes->quote_workflow_status_id';

    /**
     * @param  array<int, WorkflowStatusGroup>  $groups
     */
    public function __construct(
        private readonly ReportBranchQuery $branchQuery,
        private readonly QuoteCountAggregator $aggregator,
        private readonly array $groups,
    ) {}

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites = null): IndicatorResult
    {
        $targetIds = $this->targetStatusIds();

        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $range, $operators, $sites, $targetIds), 'distinct quotes.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $range, $operators, $sites, $targetIds), 'distinct quotes.id'),
        );
    }

    /**
     * Step 1: every status id whose `group` matches, across every workflow.
     *
     * @return array<int, int>
     */
    private function targetStatusIds(): array
    {
        return QuoteWorkflowStatus::query()
            ->whereIn('group', array_map(static fn (WorkflowStatusGroup $group): string => $group->value, $this->groups))
            ->pluck('id')
            ->all();
    }

    /**
     * Steps 2-3: the branch query + the correlated activity_log match.
     *
     * @param  array<int, int>  $categoryIds
     * @param  array<int, int>  $targetIds
     */
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites, array $targetIds): Builder
    {
        return $this->branchQuery->build($categoryIds, $actor, $operators, $sites)
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->whereExists(function (QueryBuilder $sub) use ($targetIds, $range): void {
                $sub->selectRaw('1')
                    ->from('activity_log as a')
                    ->join('quote_workflow_statuses as logged_status', 'logged_status.id', '=', self::LOGGED_STATUS_COLUMN)
                    ->whereColumn('a.subject_id', 'quotes.opportunity_id')
                    ->where('a.log_name', self::LOG_NAME)
                    ->where('a.subject_type', self::SUBJECT_TYPE)
                    ->whereIn(self::LOGGED_STATUS_COLUMN, $targetIds)
                    ->where(function (QueryBuilder $sameWorkflow): void {
                        $sameWorkflow->whereColumn('logged_status.quote_workflow_id', 'current_status.quote_workflow_id')
                            ->orWhere(function (QueryBuilder $bothGlobal): void {
                                $bothGlobal->whereNull('logged_status.quote_workflow_id')
                                    ->whereNull('current_status.quote_workflow_id');
                            });
                    })
                    ->where('a.created_at', '>=', $range->start)
                    ->where('a.created_at', '<', $range->endExclusive);
            });
    }
}
