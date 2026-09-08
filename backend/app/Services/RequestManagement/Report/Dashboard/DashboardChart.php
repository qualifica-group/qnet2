<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Dashboard;

use App\Enums\RequestManagementDashboardChartScope;

/**
 * One chart of a category section (spec 0107 data_contract, rev-3): either
 * that category's own indicators side by side (`scope = indicator`, one bar
 * per indicator column — `indicatorKey`/`indicatorLabel` null, since the
 * indicator IS the series), or the GA2 breakdown of a SINGLE indicator of
 * that category (`scope = operator`).
 *
 * The category is not carried here any more: a chart is nested INSIDE its
 * `DashboardCategory`, which owns the key and the label.
 *
 * Points of an `operator` chart are sorted value desc, label asc (AC-007);
 * those of an `indicator` chart keep the report's canonical column order, so
 * two categories stay comparable bar by bar. Since rev-3 an all-zero chart is
 * NOT dropped (AC-008 reversed by user directive 2026-09-08).
 */
final class DashboardChart
{
    /**
     * @param  array<int, DashboardPoint>  $points
     */
    public function __construct(
        public readonly string $id,
        public readonly RequestManagementDashboardChartScope $scope,
        public readonly ?string $indicatorKey,
        public readonly ?string $indicatorLabel,
        public readonly array $points,
    ) {}
}
