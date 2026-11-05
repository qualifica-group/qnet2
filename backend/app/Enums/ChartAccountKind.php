<?php

namespace App\Enums;

/**
 * Cost or revenue kind of a chart group (spec 0348, D-2). Not to be confused
 * with `CostTypeNature` (direct/indirect/general), which stays on cost types.
 */
enum ChartAccountKind: string
{
    case Cost = 'cost';
    case Revenue = 'revenue';
    case Financial = 'financial';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
