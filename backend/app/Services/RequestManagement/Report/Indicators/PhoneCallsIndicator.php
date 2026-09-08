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
 * "N. Telefonate Effettuate" (spec 0106 data_contract): NOTES created in the
 * range on a request NOT in the first workflow state — `count(notes.id)`,
 * not of requests (AC-009): two notes on the same quote count twice.
 * Excludes soft-deleted notes and notes created outside the range (AC-010).
 *
 * AC-011: `system_key IS NULL OR system_key <> 'open'` — the IS NULL branch
 * is mandatory, custom statuses carry `system_key = NULL` and, in SQL,
 * `NULL <> 'open'` evaluates to NULL (not TRUE), which a naive `<> 'open'`
 * alone would silently drop.
 */
final class PhoneCallsIndicator implements ReportIndicator
{
    public function __construct(
        private readonly ReportBranchQuery $branchQuery,
        private readonly QuoteCountAggregator $aggregator,
    ) {}

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range): IndicatorResult
    {
        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $range), 'notes.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $range), 'notes.id'),
        );
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range): Builder
    {
        return $this->branchQuery->build($categoryIds, $actor)
            ->join('notes', 'notes.quote_id', '=', 'quotes.id')
            ->whereNull('notes.deleted_at')
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->where(function (Builder $status): void {
                $status->whereNull('current_status.system_key')
                    ->orWhere('current_status.system_key', '<>', WorkflowStatusSystemKey::Open->value);
            })
            ->where('notes.created_at', '>=', $range->start)
            ->where('notes.created_at', '<', $range->endExclusive);
    }
}
