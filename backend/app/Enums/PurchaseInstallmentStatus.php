<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Payment state of a payable installment, derived from paid_amount (spec 0211,
 * D-4): a payment closes the installment, there is no partial state.
 */
enum PurchaseInstallmentStatus: string
{
    use HasMeta;

    #[Label('To pay')]
    case Unpaid = 'unpaid';

    #[Label('Paid')]
    case Paid = 'paid';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
