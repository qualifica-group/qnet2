<?php

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryManagerLabelResolver;

/**
 * Resolves the "Gestore Account" labels an Opportunity's product lines agree
 * on (spec 0080, user directive 2026-08-04 decision 1): [] (the default
 * denominations) unless every DISTINCT product category referenced by the
 * opportunity's product lines resolves to the SAME effective manager labels.
 * The comparison is on the resolved label SET, not the category id — two
 * different categories that happen to land on identical labels are not a
 * conflict.
 *
 * Relies on the caller having eager-loaded `productLines.productCategory`
 * (OpportunityService::DETAIL_RELATIONS / RequestManagementService::
 * WORK_PANEL_RELATIONS already do) so this never lazy-loads under
 * Model::preventLazyLoading().
 */
final class OpportunityManagerLabelResolver
{
    public function __construct(private readonly CategoryManagerLabelResolver $categoryLabels) {}

    /**
     * @return array<int, string>
     */
    public function resolve(Opportunity $opportunity): array
    {
        $categories = $opportunity->productLines
            ->pluck('productCategory')
            ->filter()
            ->unique('id')
            ->values();

        if ($categories->isEmpty()) {
            return [];
        }

        $resolved = $categories->map(
            fn (ProductCategory $category): array => $this->categoryLabels->effectiveManagerLabels($category),
        );

        $first = $resolved->first();

        return $resolved->every(fn (array $labels): bool => $labels === $first) ? $first : [];
    }
}
