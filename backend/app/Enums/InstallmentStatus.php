<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Collection state of an invoice installment, derived from collected_amount (spec 0194, D-13).
 */
enum InstallmentStatus: string
{
    use HasMeta;

    #[Label('Unpaid')]
    case Unpaid = 'unpaid';

    #[Label('Partially paid')]
    case PartiallyPaid = 'partially_paid';

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
