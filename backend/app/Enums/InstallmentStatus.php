<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Collection state of an invoice installment, derived from collected_amount (spec 0194, D-13; spec 0197 D-9: a collection closes the installment, no partial state).
 */
enum InstallmentStatus: string
{
    use HasMeta;

    #[Label('Not collected')]
    case Unpaid = 'unpaid';

    #[Label('Collected')]
    case Paid = 'paid';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
