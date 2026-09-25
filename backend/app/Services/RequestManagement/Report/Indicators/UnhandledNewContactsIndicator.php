<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Enums\WorkflowStatusSystemKey;
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
 * Requests still in the FIRST workflow state (`system_key = 'open'`) — one
 * already advanced past it is never counted (spec 0106 AC-013). Two columns
 * (spec 0159):
 *
 * - `nuovi_contatti` ($withinRange true, spec 0106): `quotes.created_at`
 *   inside the range.
 * - `unhandled_new_contacts` ($withinRange false, D-4): whatever the
 *   creation date.
 */
final class UnhandledNewContactsIndicator implements ReportIndicator
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
            ->where('current_status.system_key', WorkflowStatusSystemKey::Open->value);

        if (! $this->withinRange) {
            return $query;
        }

        return $range->constrain($query, 'quotes.created_at');
    }
}
