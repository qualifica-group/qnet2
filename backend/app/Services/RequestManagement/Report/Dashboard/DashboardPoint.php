<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Dashboard;

/**
 * One bar of a dashboard chart (spec 0107 data_contract): a category name or
 * a GA2 name (`users.name`, or "Non assegnato"), and the already-computed
 * value read verbatim from a `ReportRow` — never recalculated here (D-2).
 */
final class DashboardPoint
{
    public function __construct(
        public readonly string $label,
        public readonly int $value,
    ) {}
}
