<?php

namespace App\Enums;

/**
 * The system rows a QuoteWorkflowStatus set (a workflow's own, or the global
 * default set) carries (spec 0047, AC-004; moved onto the Offerta by spec
 * 0083 D-6): an initial 'open' row and the two terminal closed-outcome rows
 * 'closed_won'/'closed_lost' — those three are MANDATORY, pinned and
 * non-deletable — plus the OPTIONAL 'validated' row that closes the working
 * phase before the outcome.
 *
 * 'validated' is optional and has NO default (user directive 2026-08-03): no
 * set is created with one, and a row only becomes the validated one when the
 * client explicitly marks it (App\Services\QuoteWorkflows\
 * ValidatedStatusMarker promotes/demotes it). In the seeded catalogue only
 * "OK_Da Caricare" carries it (Database\Seeders\QualificaCatalog\
 * WorkflowStatusCatalogue::VALIDATED_STATUSES).
 *
 * Persisted as `quote_workflow_statuses.system_key` (nullable — custom rows
 * have none). Never mass-assignable (only the service that creates/syncs a
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
     * The pinned system rows placed after every custom row, in fixed ORDER:
     * 'validated' (the working phase's final step, present only when a row
     * was marked as such), then the two terminal closed-outcome rows
     * 'closed_won'/'closed_lost' (positive before negative). 'open' is pinned
     * FIRST and is not part of this tail.
     *
     * @return array<int, self>
     */
    public static function tailKeys(): array
    {
        return [self::Validated, self::ClosedWon, self::ClosedLost];
    }

    /**
     * The tail rows every set is CREATED with. 'validated' is deliberately
     * absent: it is optional and only exists once explicitly marked.
     *
     * @return array<int, self>
     */
    public static function mandatoryTailKeys(): array
    {
        return [self::ClosedWon, self::ClosedLost];
    }
}
