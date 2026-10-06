<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * State of a proforma request (spec 0193, D-8). Requests are born `pending`;
 * the future invoicing flow moves them to `issued` (D-7).
 */
enum ProformaRequestStatus: string
{
    use HasMeta;

    #[Label('Pending')]
    case Pending = 'pending';

    #[Label('Issued')]
    case Issued = 'issued';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
