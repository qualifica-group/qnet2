<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * State of an agent commission settlement (spec 0213, D-5): a draft is freely
 * recalculated or deleted, an issued one is frozen and numbered (then the agent document is received
 * and approved, spec 0267), a cancelled
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

    #[Label('Document received')]
    #[Color('amber')]
    case DocumentReceived = 'DOCUMENT_RECEIVED';

    #[Label('Approved')]
    #[Color('emerald')]
    case Approved = 'APPROVED';

    #[Label('Cancelled')]
    #[Color('red')]
    case Cancelled = 'CANCELLED';

    /**
     * Statuses of a settlement that counts as valid (spec 0267, D-1).
     *
     * @return array<int, self>
     */
    public static function validStatuses(): array
    {
        return [self::Issued, self::DocumentReceived, self::Approved];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
