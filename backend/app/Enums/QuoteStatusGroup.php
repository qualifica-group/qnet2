<?php

namespace App\Enums;

/**
 * The classification every QuoteStatus row carries (`quote_statuses.group`).
 * DELIBERATELY distinct from App\Enums\StatusGroup (pipeline / opportunity
 * statuses, still Open/Pending/Closed): for the quotes configurator the
 * terminal "closed" phase is split into its two OUTCOMES — ClosedWon (chiuso
 * positivo, the "Accettata" system row) and ClosedLost (chiuso negativo, the
 * "Rifiutata" system row). Mirrors the split already in place for
 * App\Enums\WorkflowStatusGroup, minus its Validated phase. Never
 * mass-assignable on a system row (App\Services\Statuses\SystemStatusGuard
 * rejects it outright).
 */
enum QuoteStatusGroup: string
{
    case Open = 'open';
    case Pending = 'pending';
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
