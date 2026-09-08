<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Enums\WorkflowStatusSystemKey;
use App\Models\User;
use App\Services\RequestManagement\Report\IndicatorResult;
use App\Services\RequestManagement\Report\QuoteCountAggregator;
use App\Services\RequestManagement\Report\ReportBranchQuery;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportIndicator;
use Illuminate\Database\Eloquent\Builder;

/**
 * "N. Nuovi contatti non gestiti" (spec 0106): `quotes.created_at` inside
 * the range AND the request is still in the FIRST workflow state
 * (`system_key = 'open'`) — a request created in range but already advanced
 * past the first state is not counted (AC-013).
 */
final class UnhandledNewContactsIndicator implements ReportIndicator
{
    public function __construct(
        private readonly ReportBranchQuery $branchQuery,
        private readonly QuoteCountAggregator $aggregator,
    ) {}

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range): IndicatorResult
    {
        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $range), 'quotes.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $range), 'quotes.id'),
        );
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range): Builder
    {
        return $this->branchQuery->build($categoryIds, $actor)
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->where('current_status.system_key', WorkflowStatusSystemKey::Open->value)
            ->where('quotes.created_at', '>=', $range->start)
            ->where('quotes.created_at', '<', $range->endExclusive);
    }
}
