<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A dashboard chart's series shape (spec 0107, D-3): `Category` compares the
 * selected branches on one indicator (one bar per category), `Operator`
 * compares the GA2 breakdown of ONE branch on one indicator (one bar per
 * operator, "Non assegnato" included). Which scopes a response contains is
 * decided by `row_mode` — `total_only` -> Category only, `operators_only`
 * -> Operator only, `all` -> both, Category first.
 */
enum RequestManagementDashboardChartScope: string
{
    case Category = 'category';
    case Operator = 'operator';
}
