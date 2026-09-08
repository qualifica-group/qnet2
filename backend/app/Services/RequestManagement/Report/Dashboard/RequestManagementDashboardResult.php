<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Dashboard;

/**
 * The assembled `{ summary, charts }` payload of spec 0107 data_contract.
 * `applied` (the echoed filters) is NOT part of this — it is built by the
 * controller straight from the validated request, this object carries only
 * what RequestManagementDashboardBuilder computed.
 */
final class RequestManagementDashboardResult
{
    /**
     * @param  array<int, DashboardSummaryItem>  $summary
     * @param  array<int, DashboardChart>  $charts
     */
    public function __construct(
        public readonly array $summary,
        public readonly array $charts,
    ) {}
}
