<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Half-day part of an absence request (spec 0304, D-8).
 */
enum AbsenceDayPart: string
{
    use HasMeta;

    #[Label('Morning')]
    #[Color('amber')]
    case Morning = 'morning';

    #[Label('Afternoon')]
    #[Color('blue')]
    case Afternoon = 'afternoon';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
