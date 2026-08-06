<?php

namespace App\Enums;

/**
 * The classification every QuoteWorkflowStatus row carries
 * (`quote_workflow_statuses.group`) — the "stato dell'offerta" grouping
 * (spec 0047, moved onto the Offerta by spec 0083 D-6). DELIBERATELY
 * distinct from App\Enums\StatusGroup (pipeline / opportunity statuses,
 * still Open/Pending/Closed): here the working phase runs Open -> Pending ->
 * Validated (esito accertato, non ancora chiuso), then the terminal "closed"
 * phase is split into its two OUTCOMES — ClosedWon (esito positivo) and
 * ClosedLost (esito negativo). Never mass-assignable on a system row
 * (App\Services\QuoteWorkflows\WorkflowStatusWriter rejects a group change
 * on a pinned row).
 */
enum WorkflowStatusGroup: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Validated = 'validated';
    case ClosedWon = 'closed_won';
    case ClosedLost = 'closed_lost';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
