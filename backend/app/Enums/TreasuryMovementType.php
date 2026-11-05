<?php

namespace App\Enums;

/** Kind of a manual treasury movement (spec 0360). */
enum TreasuryMovementType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
