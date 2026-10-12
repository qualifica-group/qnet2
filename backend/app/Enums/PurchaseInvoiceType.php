<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Kind of a payable document (spec 0211, D-1): a supplier proforma or invoice.
 */
enum PurchaseInvoiceType: string
{
    use HasMeta;

    #[Label('Proforma')]
    case Proforma = 'proforma';

    #[Label('Invoice')]
    case Invoice = 'invoice';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
