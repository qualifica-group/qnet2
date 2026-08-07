<?php

namespace App\Enums;

/**
 * The system rows a QuoteWorkflowStatus set (a workflow's own, or the global
 * default set) carries (spec 0047, AC-004; moved onto the Offerta by spec
 * 0083 D-6): an initial 'open' row and the two terminal closed-outcome rows
 * 'closed_won'/'closed_lost'. All three are MANDATORY, pinned and
 * non-deletable, and every set is created with them.
 *
 * There is NO system row for the "validated" phase (user directive
 * 2026-08-07, superseding the optional 'validated' key of 2026-08-03):
 * "Validato" survives only as a WorkflowStatusGroup value, freely assignable
 * to any number of ordinary custom rows through the configurator's group
 * select. Rows that carried the old key were unmarked (keeping their
 * `group`) by the 2026_08_07_100000 migration.
 *
 * Persisted as `quote_workflow_statuses.system_key` (nullable — custom rows
 * have none). Never mass-assignable (only the service that creates/syncs a
 * workflow's status set writes it).
 */
enum WorkflowStatusSystemKey: string
{
    case Open = 'open';
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
     * The pinned system rows placed after every custom row, in fixed ORDER:
     * the two terminal closed-outcome rows (positive before negative).
     * 'open' is pinned FIRST and is not part of this tail.
     *
     * @return array<int, self>
     */
    public static function tailKeys(): array
    {
        return [self::ClosedWon, self::ClosedLost];
    }
}
