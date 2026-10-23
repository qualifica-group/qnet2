<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Who holds a stock row (spec 0307): free goods sit in a site, assigned ones
 * are in the care of a user. Derived from `user_id`, never stored.
 */
enum StockHolder: string
{
    use HasMeta;

    #[Label('Free')]
    #[Color('green')]
    case Free = 'FREE';

    #[Label('Assigned')]
    #[Color('amber')]
    case Assigned = 'ASSIGNED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
