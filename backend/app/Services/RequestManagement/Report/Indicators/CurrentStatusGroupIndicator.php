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
 * Requests whose CURRENT workflow status belongs to one of $groups — the
 * status history is never read. Two uses:
 *
 * - `current_potentials` ($createdWithinRange false, spec 0159 D-5): whatever
 *   the picked range.
 * - `associati`/`trattative_concluse`/`invio_presa_in_carico`
 *   ($createdWithinRange true, spec 0170 D-1/D-2): `quotes.created_at`
 *   inside the range, since no closing date exists without the history.
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
        private readonly bool $createdWithinRange,
    ) {}

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites = null, RequestModule $module = RequestModule::Requests): IndicatorResult
    {
        return $this->aggregator->partitioned($this->query($categoryIds, $actor, $range, $operators, $sites, $module), 'quotes.id');
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites, RequestModule $module): Builder
    {
        $query = $this->branchQuery->build($categoryIds, $actor, $operators, $sites, $module)
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->whereIn('current_status.group', array_map(static fn (WorkflowStatusGroup $group): string => $group->value, $this->groups));

        if (! $this->createdWithinRange) {
            return $query;
        }

        return $range->constrain($query, 'quotes.created_at');
    }
}
