<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Dashboard;

/**
 * One category section of the dashboard (spec 0107 D-10, rev-3): a selected
 * branch with its OWN summary tiles — its already-computed TOTALE row, all
 * eleven indicator columns, zeros included (D-11) — and its own charts.
 *
 * A selected branch always gets a section, even when every one of its numbers
 * is 0: an empty week is an answer, not a reason to hide the category.
 */
final class DashboardCategory
{
    /**
     * @param  array<int, DashboardSummaryItem>  $summary
     * @param  array<int, DashboardChart>  $charts
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $summary,
        public readonly array $charts,
    ) {}
}
