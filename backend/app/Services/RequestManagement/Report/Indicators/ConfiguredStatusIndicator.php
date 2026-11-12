<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Enums\ReportStatusColumn;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\Report\IndicatorResult;
use App\Services\RequestManagement\Report\QuoteCountAggregator;
use App\Services\RequestManagement\Report\ReportBranchQuery;
use App\Services\RequestManagement\Report\ReportColumnStatusMap;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportIndicator;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\ReportSiteFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every status-based statistics column (spec 0391 D-3): requests whose
 * CURRENT working status is one of the statuses configured for $column. A
 * column with no status configured counts nothing. $withinRange adds
 * `quotes.created_at` inside the range: the "(nel periodo selezionato)" twin
 * and the period-bound Associati / Trattative concluse / Invio presa in
 * carico; the twin without a period counts whatever the creation date.
 */
final class ConfiguredStatusIndicator implements ReportIndicator
{
    public function __construct(
        private readonly ReportBranchQuery $branchQuery,
        private readonly QuoteCountAggregator $aggregator,
        private readonly ReportColumnStatusMap $statuses,
        private readonly ReportStatusColumn $column,
        private readonly bool $withinRange,
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
        // No configured status: `whereIn(..., [])` matches nothing, so the column is 0.
        // QuoteCountAggregator splits by the current status, joined as `current_status`.
        $query = $this->branchQuery->build($categoryIds, $actor, $operators, $sites, $module)
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->whereIn('current_status.id', $this->statuses->idsFor($this->column));

        return $this->withinRange ? $range->constrain($query, 'quotes.created_at') : $query;
    }
}
