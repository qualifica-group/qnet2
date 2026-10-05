<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Payment circuit of a financial-account card (spec 0189).
 */
enum FinancialCardCircuit: string
{
    use HasMeta;

    #[Label('Visa')]
    case Visa = 'visa';

    #[Label('Mastercard')]
    case Mastercard = 'mastercard';

    #[Label('American Express')]
    case Amex = 'amex';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
