<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Estimate or final marker of an invoice (spec 0194, D-4).
 */
enum InvoiceTag: string
{
    use HasMeta;

    #[Label('Estimate')]
    case Estimate = 'estimate';

    #[Label('Final')]
    case Final = 'final';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
