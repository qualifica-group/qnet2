<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Kind of an active invoicing document (spec 0194, D-3): a proforma becomes an invoice once the external number is registered.
 */
enum InvoiceType: string
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
