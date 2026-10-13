<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Kind of a commission ledger movement (spec 0212): an accrual adds to the
 * amount earned, a reversal takes back a previous one.
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

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
