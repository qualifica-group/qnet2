<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * State of an agent commission settlement (spec 0213, D-5): a draft is freely
 * recalculated or deleted, an issued one is frozen and numbered, a cancelled
 * one keeps its number but releases its movements.
 */
enum CommissionSettlementStatus: string
{
    use HasMeta;

    #[Label('Draft')]
    #[Color('gray')]
    case Draft = 'DRAFT';

    #[Label('Issued')]
    #[Color('green')]
    case Issued = 'ISSUED';

    #[Label('Cancelled')]
    #[Color('red')]
    case Cancelled = 'CANCELLED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
