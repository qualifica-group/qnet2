<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * FatturaPA payment condition (CondizioniPagamento) of a payment method (spec 0325, D-1).
 */
enum PaymentCondition: string
{
    use HasMeta;

    #[Label('Installments')]
    case Installments = 'TP01';

    #[Label('Full payment')]
    case Full = 'TP02';

    #[Label('Advance payment')]
    case Advance = 'TP03';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
