<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Dashboard;

/**
 * The assembled `{ summary, categories }` payload of spec 0107 data_contract
 * (rev-3): the overall tiles computed on the union of the selected branches
 * (D-8 — never a sum of the sections below), then one section per selected
 * category, each with its own tiles and charts (D-10).
 *
 * `applied` (the echoed filters) is NOT part of this — it is built by the
 * controller straight from the validated request, this object carries only
 * what RequestManagementDashboardBuilder computed.
 */
final class RequestManagementDashboardResult
{
    /**
     * @param  array<int, DashboardSummaryItem>  $summary
     * @param  array<int, DashboardCategory>  $categories
     */
    public function __construct(
        public readonly array $summary,
        public readonly array $categories,
    ) {}
}
