<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A dashboard chart's series shape (spec 0107, D-3, rev-3): every chart now
 * belongs to ONE category section (D-10), so the category is no longer a
 * series of its own. `Indicator` compares that category's own indicators
 * (one bar per indicator column, all eleven, zeros included — D-11);
 * `Operator` breaks ONE indicator of that category down by GA2 (one bar per
 * operator, "Non assegnato" included). Which scopes a section contains is
 * decided by `row_mode` — `total_only` -> Indicator only, `operators_only`
 * -> Operator only, `all` -> both, Indicator first.
 */
enum RequestManagementDashboardChartScope: string
{
    case Indicator = 'indicator';
    case Operator = 'operator';
}
