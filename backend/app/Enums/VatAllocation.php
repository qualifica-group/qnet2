<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * How the VAT is spread over the installments of a payment method (spec 0194, D-10/D-11).
 */
enum VatAllocation: string
{
    use HasMeta;

    #[Label('Split')]
    case Split = 'split';

    #[Label('On first installment')]
    case First = 'first';

    #[Label('On last installment')]
    case Last = 'last';

    #[Label('First installment is VAT only')]
    case VatFirst = 'vat_first';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
