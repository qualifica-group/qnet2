<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Kind of a company closure (spec 0304, D-5): `holiday` behaves like a public
 * holiday (no balance, target 0), `collective_leave` generates approved
 * absences of the chosen type.
 */
enum CompanyClosureKind: string
{
    use HasMeta;

    #[Label('Holiday')]
    #[Color('blue')]
    case Holiday = 'holiday';

    #[Label('Collective leave')]
    #[Color('amber')]
    case CollectiveLeave = 'collective_leave';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
