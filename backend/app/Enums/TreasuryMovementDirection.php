<?php

namespace App\Enums;

/** Sign of a balance adjustment (spec 0355): in raises the balance, out lowers it. */
enum TreasuryMovementDirection: string
{
    case In = 'in';
    case Out = 'out';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
