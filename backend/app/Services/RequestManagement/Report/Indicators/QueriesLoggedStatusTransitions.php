<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Services\RequestManagement\Report\ReportDateRange;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The correlated `activity_log` sub-query shared by the two indicators that
 * read the status history (spec 0106 D-1/D-3, spec 0170): a working-status
 * change logged on the request's Opportunity inside the range, matched via
 * the arrow operator on `properties->...->quote_workflow_status_id` (never
 * whereRaw/json_extract, AC-023). The outer query must join the quote's
 * current status as `current_status`.
 *
 * D-3 attribution to one Quote: the logged target status (`logged_status`)
 * must belong to the SAME `quote_workflow_id` as the quote's CURRENT status —
 * compared null-safely, since two quotes both on the GLOBAL default set
 * (`quote_workflow_id IS NULL`) are on the very same set and must still
 * match, which a plain `=` would miss (SQL `NULL = NULL` is NULL, not TRUE).
 */
trait QueriesLoggedStatusTransitions
{
    private const string LOG_NAME = 'opportunities';

    private const string SUBJECT_TYPE = 'opportunity';

    private const string LOGGED_STATUS_COLUMN = 'a.properties->attributes->quote_workflow_status_id';

    private const string LOGGED_PREVIOUS_STATUS_COLUMN = 'a.properties->old->quote_workflow_status_id';

    private function loggedTransitionOnCurrentWorkflow(QueryBuilder $sub, ReportDateRange $range): QueryBuilder
    {
        $sub->selectRaw('1')
            ->from('activity_log as a')
            ->join('quote_workflow_statuses as logged_status', 'logged_status.id', '=', self::LOGGED_STATUS_COLUMN)
            ->whereColumn('a.subject_id', 'quotes.opportunity_id')
            ->where('a.log_name', self::LOG_NAME)
            ->where('a.subject_type', self::SUBJECT_TYPE)
            ->where(function (QueryBuilder $sameWorkflow): void {
                $sameWorkflow->whereColumn('logged_status.quote_workflow_id', 'current_status.quote_workflow_id')
                    ->orWhere(function (QueryBuilder $bothGlobal): void {
                        $bothGlobal->whereNull('logged_status.quote_workflow_id')
                            ->whereNull('current_status.quote_workflow_id');
                    });
            });

        return $range->constrain($sub, 'a.created_at');
    }
}
