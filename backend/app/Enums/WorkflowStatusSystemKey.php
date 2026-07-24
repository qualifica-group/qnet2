<?php

namespace App\Enums;

/**
 * The mandatory system rows every OpportunityWorkflowStatus set (a workflow's
 * own, or the global default set) carries (spec 0047, AC-004): an initial
 * 'open' row, the 'validated' row that closes the working phase before the
 * outcome, and the two terminal closed-outcome rows 'closed_won'/'closed_lost'
 * — all pinned and non-deletable. Persisted as
 * `opportunity_workflow_statuses.system_key` (nullable — custom rows have
 * none). Never mass-assignable (only the service that creates/syncs a
 * workflow's status set writes it).
 */
enum WorkflowStatusSystemKey: string
{
    case Open = 'open';
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

    /**
     * The pinned system rows placed after every custom row, in fixed order:
     * 'validated' (the working phase's final step), then the two terminal
     * closed-outcome rows 'closed_won'/'closed_lost' (positive before
     * negative). 'open' is pinned FIRST and is not part of this tail.
     *
     * @return array<int, self>
     */
    public static function tailKeys(): array
    {
        return [self::Validated, self::ClosedWon, self::ClosedLost];
    }
}
