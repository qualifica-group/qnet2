<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Enums\WorkflowStatusGroup;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\RequestManagement\RequestModule;
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
 * "N. Potenziali associati (nel periodo selezionato)" (spec 0106 D-2):
 * requests with a logged transition, inside the range, toward a status whose
 * `group` is one of $groups (pending/validated). Since spec 0170 it is the
 * only column besides "Presa Appuntamenti" that reads the status history:
 * the closed_won columns look at the current status instead.
 *
 * transition_resolution (D-3), three steps:
 *  1. targetStatusIds() resolves every status id whose `group` is one of
 *     $groups, GLOBALLY (no workflow filter yet). A branch whose catalogue
 *     has NO status in that group naturally resolves to an empty id list,
 *     and `whereIn(..., [])` matches nothing — 0 with no special-casing.
 *  2-3. whereExists on activity_log with the D-3 disambiguation:
 *     QueriesLoggedStatusTransitions.
 */
final class WorkflowTransitionIndicator implements ReportIndicator
{
    use QueriesLoggedStatusTransitions;

    /**
     * @param  array<int, WorkflowStatusGroup>  $groups
     */
    public function __construct(
        private readonly ReportBranchQuery $branchQuery,
        private readonly QuoteCountAggregator $aggregator,
        private readonly array $groups,
    ) {}

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites = null, RequestModule $module = RequestModule::Requests): IndicatorResult
    {
        $targetIds = $this->targetStatusIds();

        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $range, $operators, $sites, $targetIds, $module), 'distinct quotes.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $range, $operators, $sites, $targetIds, $module), 'distinct quotes.id'),
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
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites, array $targetIds, RequestModule $module): Builder
    {
        return $this->branchQuery->build($categoryIds, $actor, $operators, $sites, $module)
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->whereExists(function (QueryBuilder $sub) use ($targetIds, $range): void {
                $this->loggedTransitionOnCurrentWorkflow($sub, $range)
                    ->whereIn(self::LOGGED_STATUS_COLUMN, $targetIds);
            });
    }
}
