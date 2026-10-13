<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Kind of a commission ledger movement (spec 0212): an accrual adds to the
 * amount earned, a reversal takes back a previous one, an adjustment is a
 * signed manual correction entered by Accounting (spec 0213, D-7).
 */
enum CommissionAccrualType: string
{
    use HasMeta;

    #[Label('Accrual')]
    #[Color('green')]
    case Accrual = 'ACCRUAL';

    #[Label('Reversal')]
    #[Color('red')]
    case Reversal = 'REVERSAL';

    #[Label('Adjustment')]
    #[Color('blue')]
    case Adjustment = 'ADJUSTMENT';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
