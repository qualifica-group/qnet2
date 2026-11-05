<?php

namespace App\Enums;

/**
 * Why the residual of an installment is written off (spec 0361, D-2):
 * rounding = abbuono, bad_debt = stralcio.
 */
enum WriteOffReason: string
{
    case Rounding = 'rounding';
    case BadDebt = 'bad_debt';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
