<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\User;
use App\RequestManagement\RequestModule;

/**
 * The report branches available to an actor (spec 0106 rev-2, D-11/D-12):
 * the six branches of config, filtered to those with AT LEAST ONE request
 * in the actor's own visibility scope, ALL-TIME (no date filter — D-12,
 * the dialog loads this before any date is picked). Reuses
 * ReportBranchQuery verbatim, so this can never diverge from the scope the
 * report itself applies (AC-027) or from the branch expansion to
 * descendants (AC-029).
 *
 * Spec 0130: $module (defaulting to RequestModule::Requests) narrows "at
 * least one request" to the module's own D-2 row-state filter too, so an
 * Iscritti actor is never offered a branch whose only requests are outside
 * `validated`/`closed_won`.
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
    public function available(?User $actor, RequestModule $module = RequestModule::Requests): array
    {
        $available = [];

        foreach ($this->branches->resolve() as $branch) {
            if ($this->branchQuery->build($branch->categoryIds, $actor, ReportOperatorFilter::all(), module: $module)->exists()) {
                $available[] = ['key' => $branch->key, 'label' => $branch->label];
            }
        }

        return $available;
    }
}
