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
 * "N. Telefonate Effettuate" (spec 0106 data_contract, rev-3): NOTES created
 * in the range on a request of the branch and written BY the request's own
 * GA2 Operatore — `count(notes.id)`, not of requests (AC-009): two notes on
 * the same request count twice. Excludes soft-deleted notes, general notes
 * (`quote_id IS NULL`) and notes created outside the range (AC-010).
 *
 * Two deliberate rules:
 *
 * - Only requests NOT in the first workflow state (`system_key <> 'open'`):
 *   "note inserite per ogni offerta che non ha il primo stato" (user
 *   directive 2026-09-18, which reinstates the AC-010 restriction the
 *   2026-09-09 directive had dropped).
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

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites = null, RequestModule $module = RequestModule::Requests): IndicatorResult
    {
        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $range, $operators, $sites, $module), 'notes.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $range, $operators, $sites, $module), 'notes.id'),
        );
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators, ?ReportSiteFilter $sites, RequestModule $module): Builder
    {
        return $this->branchQuery->build($categoryIds, $actor, $operators, $sites, $module)
            ->join('quote_workflow_statuses as current_status', 'current_status.id', '=', 'quotes.quote_workflow_status_id')
            ->where(function (Builder $status): void {
                $status->whereNull('current_status.system_key')
                    ->orWhere('current_status.system_key', '<>', WorkflowStatusSystemKey::Open->value);
            })
            ->join('notes', 'notes.quote_id', '=', 'quotes.id')
            ->whereNull('notes.deleted_at')
            ->whereColumn('notes.user_id', 'quotes.operator_id')
            ->where('notes.created_at', '>=', $range->start)
            ->where('notes.created_at', '<', $range->endExclusive);
    }
}
