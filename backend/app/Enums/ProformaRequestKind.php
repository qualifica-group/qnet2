<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * What a proforma request bills (spec 0193, D-1): the consultancy lines of the
 * work order as a whole, or the institution lines of one supplier.
 */
enum ProformaRequestKind: string
{
    use HasMeta;

    #[Label('Consultancy')]
    case Consultancy = 'consultancy';

    #[Label('Institution')]
    case Institution = 'institution';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
