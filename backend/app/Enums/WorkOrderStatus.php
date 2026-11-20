<?php

namespace App\Enums;

/**
 * The four groups of a "Stato lavorazione" (spec 0322, D-7): open, in
 * progress, completed (positive closure), closed (negative closure). Spec
 * 0395 retired the status computed from the progress with these values: the
 * status of a commessa is now its persisted working phase (WorkOrderWorkPhase).
 */
enum WorkOrderStatus: string
{
    case Open = 'open';

    case InProgress = 'in_progress';

    case Completed = 'completed';

    case Closed = 'closed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
