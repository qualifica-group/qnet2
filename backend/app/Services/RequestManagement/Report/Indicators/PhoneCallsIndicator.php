<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Models\User;
use App\Services\RequestManagement\Report\IndicatorResult;
use App\Services\RequestManagement\Report\QuoteCountAggregator;
use App\Services\RequestManagement\Report\ReportBranchQuery;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportIndicator;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\ReportSiteFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * "N. Telefonate Effettuate" (spec 0106 data_contract, rev-3): NOTES created
 * in the range on a request of the branch and written BY the request's own
 * GA2 Operatore — `count(notes.id)`, not of requests (AC-009): two notes on
 * the same request count twice. Excludes soft-deleted notes, general notes
 * (`quote_id IS NULL`) and notes created outside the range (AC-010).
 *
 * Two deliberate rules, direttiva utente 2026-09-09:
 *
 * - NO filter on the current workflow status. Every note of the module
 *   counts, first state included: the column measures the operator's calls,
 *   not the progress of the request (rev-3 replaces the old
 *   `system_key <> 'open'` restriction of AC-010/AC-011).
 * - `notes.user_id = quotes.operator_id` (AC-011, rev-3): only what the GA2
 *   wrote on their OWN requests. A note by anyone else (admin, colleague)
 *   is not a call that operator made, so it is not counted at all — and a
 *   request with no operator can never match, which keeps the "Non
 *   assegnato" row at 0 for this column by construction.
 */
final class PhoneCallsIndicator implements ReportIndicator
{
    public function __construct(
        private readonly ReportBranchQuery $branchQuery,
        private readonly QuoteCountAggregator $aggregator,
    ) {}

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites = null): IndicatorResult
    {
        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $range, $operators, $sites), 'notes.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $range, $operators, $sites), 'notes.id'),
        );
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites): Builder
    {
        return $this->branchQuery->build($categoryIds, $actor, $operators, $sites)
            ->join('notes', 'notes.quote_id', '=', 'quotes.id')
            ->whereNull('notes.deleted_at')
            ->whereColumn('notes.user_id', 'quotes.operator_id')
            ->where('notes.created_at', '>=', $range->start)
            ->where('notes.created_at', '<', $range->endExclusive);
    }
}
