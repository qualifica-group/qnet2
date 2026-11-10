<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The working phase behind the status tabs of the Commesse page, the five
 * legacy `orders.status` values. NEVER persisted: resolved by
 * App\Tables\WorkOrders\WorkOrderWorkPhaseColumn from the group of the
 * commessa's "Stato lavorazione" and, for the `closed` group, from its contract.
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

    /** The "Stato lavorazione" group this phase reads. */
    public function statusGroup(): WorkOrderStatus
    {
        return match ($this) {
            self::ToPlan => WorkOrderStatus::Open,
            self::InProgress => WorkOrderStatus::InProgress,
            self::Completed => WorkOrderStatus::Completed,
            self::Cancelled, self::Terminated => WorkOrderStatus::Closed,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
