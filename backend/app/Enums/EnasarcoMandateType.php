<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * ENASARCO mandate kind of an agent (spec 0212): decides which contribution
 * rates and ceilings apply once M2 computes them.
 */
enum EnasarcoMandateType: string
{
    use HasMeta;

    #[Label('Single-principal')]
    case Mono = 'mono';

    #[Label('Multi-principal')]
    case Pluri = 'pluri';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
