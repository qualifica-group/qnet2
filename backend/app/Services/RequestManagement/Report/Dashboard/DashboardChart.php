<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Dashboard;

use App\Enums\RequestManagementDashboardChartScope;

/**
 * One chart of the dashboard response (spec 0107 data_contract): a single
 * indicator, either compared ACROSS the selected categories (`scope =
 * category`, `categoryKey`/`categoryLabel` null) or broken down by GA2
 * WITHIN one category (`scope = operator`). `points` is already sorted
 * (value desc, label asc — AC-007) and never all-zero (AC-008: such a chart
 * is dropped before construction, see RequestManagementDashboardBuilder).
 */
final class DashboardChart
{
    /**
     * @param  array<int, DashboardPoint>  $points
     */
    public function __construct(
        public readonly string $id,
        public readonly RequestManagementDashboardChartScope $scope,
        public readonly ?string $categoryKey,
        public readonly ?string $categoryLabel,
        public readonly string $indicatorKey,
        public readonly string $indicatorLabel,
        public readonly array $points,
    ) {}
}
