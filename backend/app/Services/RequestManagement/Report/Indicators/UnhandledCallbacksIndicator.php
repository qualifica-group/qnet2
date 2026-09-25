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
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Callbacks on a request that is NOT closed (neither `closed_won` nor
 * `closed_lost` group); every other state counts. Two columns (spec 0159):
 *
 * - `unhandled_callbacks` ($withinRange false, user directive 2026-09-18):
 *   `quotes.next_callback_at` on or before TODAY — overdue or due now,
 *   whatever the picked range.
 * - `richiami` ($withinRange true, D-3): `quotes.next_callback_at` inside
 *   the picked range, future days of the range included.
 *
 * `next_callback_reminded_at` stays unusable as an "already handled" marker
 * (spec 0106 D-4: never written in production).
 */
final class UnhandledCallbacksIndicator implements ReportIndicator
{
    public function __construct(
        private readonly ReportBranchQuery $branchQuery,
        private readonly QuoteCountAggregator $aggregator,
        private readonly bool $withinRange,
    ) {}

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites = null, RequestModule $module = RequestModule::Requests): IndicatorResult
    {
        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $range, $operators, $sites, $module), 'quotes.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $range, $operators, $sites, $module), 'quotes.id'),
        );
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites, RequestModule $module): Builder
    {
        $query = $this->branchQuery->build($categoryIds, $actor, $operators, $sites, $module)
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->whereNotIn('current_status.group', [WorkflowStatusGroup::ClosedWon->value, WorkflowStatusGroup::ClosedLost->value]);

        if ($this->withinRange) {
            return $query->where('quotes.next_callback_at', '>=', $range->start)
                ->where('quotes.next_callback_at', '<', $range->endExclusive);
        }

        return $query->where('quotes.next_callback_at', '<', CarbonImmutable::today()->addDay());
    }
}
