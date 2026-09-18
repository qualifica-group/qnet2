<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\User;
use App\RequestManagement\RequestModule;

/**
 * The report branches available to an actor (spec 0106 rev-2, D-11/D-12):
 * the reportable branches (spec 0131) and their subcategories (user
 * directive 2026-09-18), filtered to those with AT LEAST ONE request
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
     * `depth`/`parent_key` (user directive 2026-09-18) feed the separate
     * "Sottocategorie" picker; the list keeps the resolver's tree order.
     *
     * @return array<int, array{key: string, label: string, depth: int, parent_key: string|null}>
     */
    public function available(?User $actor, RequestModule $module = RequestModule::Requests): array
    {
        $branches = $this->branches->resolve();

        // Step 1: every category an in-scope request is filed under, in ONE
        // query — a branch per subcategory would otherwise cost one EXISTS each.
        $usedCategoryIds = array_flip($this->usedCategoryIds($branches, $actor, $module));

        // Step 2: a branch is on offer when its subtree holds one of them.
        $available = [];
        foreach ($branches as $branch) {
            foreach ($branch->categoryIds as $categoryId) {
                if (isset($usedCategoryIds[$categoryId])) {
                    $available[] = [
                        'key' => $branch->key,
                        'label' => $branch->label,
                        'depth' => $branch->depth,
                        'parent_key' => $branch->parentKey,
                    ];

                    break;
                }
            }
        }

        return $available;
    }

    /**
     * @param  array<int, ReportBranch>  $branches
     * @return array<int, int>
     */
    private function usedCategoryIds(array $branches, ?User $actor, RequestModule $module): array
    {
        $allCategoryIds = array_values(array_unique(array_merge([], ...array_map(
            static fn (ReportBranch $branch): array => $branch->categoryIds,
            $branches,
        ))));

        if ($allCategoryIds === []) {
            return [];
        }

        return $this->branchQuery
            ->build($allCategoryIds, $actor, ReportOperatorFilter::all(), module: $module)
            ->join('opportunity_product_lines as filed_line', 'filed_line.opportunity_id', '=', 'opportunities.id')
            ->whereIn('filed_line.product_category_id', $allCategoryIds)
            ->distinct()
            ->pluck('filed_line.product_category_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
