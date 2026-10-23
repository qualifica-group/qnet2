<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Kind of a stock movement (spec 0307, D-5/D-11): decides which side of the
 * movement (source, destination) is filled and what the form asks for.
 */
enum StockMovementType: string
{
    use HasMeta;

    #[Label('Load')]
    #[Color('green')]
    case Load = 'LOAD';

    #[Label('Unload')]
    #[Color('red')]
    case Unload = 'UNLOAD';

    #[Label('Transfer')]
    #[Color('blue')]
    case Transfer = 'TRANSFER';

    #[Label('Assignment')]
    #[Color('amber')]
    case Assign = 'ASSIGN';

    #[Label('Return')]
    #[Color('gray')]
    case Return = 'RETURN';

    #[Label('Adjustment')]
    #[Color('purple')]
    case Adjustment = 'ADJUSTMENT';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
