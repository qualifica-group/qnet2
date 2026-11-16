<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The working phase behind the status tabs of the Commesse page: the five
 * legacy `orders.status` values, persisted in `work_orders.work_phase` and
 * independent from "Stato lavorazione" (spec 0395). It is the commessa's
 * status everywhere (grid `status` column, detail badge, chat, update request). Moved only by
 * App\Services\WorkOrders\WorkOrderWorkPhaseTracker and the legacy import.
 */
enum WorkOrderWorkPhase: string
{
    use HasMeta;

    #[Label('To plan')]
    #[Color('blue')]
    case ToPlan = 'to_plan';

    #[Label('In progress')]
    #[Color('amber')]
    case InProgress = 'in_progress';

    #[Label('Completed')]
    #[Color('green')]
    case Completed = 'completed';

    #[Label('Cancelled')]
    #[Color('red')]
    case Cancelled = 'cancelled';

    #[Label('Terminated')]
    #[Color('slate')]
    case Terminated = 'terminated';

    /**
     * Still to do or being done (legacy statuses 1 and 2): "Richiedi aggiornamento"
     * is offered and the chat is writable (spec 0352 D-3, spec 0359) only then.
     */
    public function isOngoing(): bool
    {
        return $this === self::ToPlan || $this === self::InProgress;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
