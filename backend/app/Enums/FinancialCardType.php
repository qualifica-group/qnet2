<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Card kind (spec 0189): a credit card must be linked to a bank account, a
 * prepaid one may stand alone (D-2).
 */
enum FinancialCardType: string
{
    use HasMeta;

    #[Label('Credit')]
    case Credit = 'credit';

    #[Label('Prepaid')]
    case Prepaid = 'prepaid';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
