<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\User;

/**
 * The report branches available to an actor (spec 0106 rev-2, D-11/D-12):
 * the six branches of config, filtered to those with AT LEAST ONE request
 * in the actor's own visibility scope, ALL-TIME (no date filter — D-12,
 * the dialog loads this before any date is picked). Reuses
 * ReportBranchQuery verbatim, so this can never diverge from the scope the
 * report itself applies (AC-027) or from the branch expansion to
 * descendants (AC-029).
 */
final class ReportCategoryAvailabilityResolver
{
    public function __construct(
        private readonly ReportBranchResolver $branches,
        private readonly ReportBranchQuery $branchQuery,
    ) {}

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function available(?User $actor): array
    {
        $available = [];

        foreach ($this->branches->resolve() as $branch) {
            if ($this->branchQuery->build($branch->categoryIds, $actor)->exists()) {
                $available[] = ['key' => $branch->key, 'label' => $branch->label];
            }
        }

        return $available;
    }
}
