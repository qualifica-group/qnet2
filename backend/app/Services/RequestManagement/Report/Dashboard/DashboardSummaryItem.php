<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Dashboard;

/**
 * One numeric summary card (spec 0107 data_contract): `value` is the REAL
 * total over the union of every selected branch (D-8), never a sum of the
 * per-category totals — read straight off the synthetic branch's own TOTALE
 * `ReportRow`, the exact same code path a real branch goes through.
 */
final class DashboardSummaryItem
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly int $value,
    ) {}
}
