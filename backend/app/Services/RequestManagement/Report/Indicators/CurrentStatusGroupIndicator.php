<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Enums\WorkflowStatusGroup;
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

/**
 * Requests whose CURRENT workflow status belongs to one of $groups, whatever
 * the picked range (spec 0159 D-5, `current_potentials`): the range-free
 * counterpart of WorkflowTransitionIndicator, which instead asks for a
 * transition logged inside the range.
 */
final class CurrentStatusGroupIndicator implements ReportIndicator
{
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
        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $operators, $sites, $module), 'quotes.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $operators, $sites, $module), 'quotes.id'),
        );
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function query(array $categoryIds, ?User $actor, ReportOperatorFilter $operators, ?ReportSiteFilter $sites, RequestModule $module): Builder
    {
        return $this->branchQuery->build($categoryIds, $actor, $operators, $sites, $module)
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->whereIn('current_status.group', array_map(static fn (WorkflowStatusGroup $group): string => $group->value, $this->groups));
    }
}
