<?php

namespace App\Enums;

/**
 * Level of a `chart_accounts` row (spec 0348, D-8): GROUP = Mastro (top level,
 * carries the cost/revenue kind), ACCOUNT = Conto (child of a group). The
 * third level (sub-account) lives in `cost_types` / `revenue_types`.
 */
enum ChartAccountLevel: string
{
    case Group = 'group';
    case Account = 'account';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
