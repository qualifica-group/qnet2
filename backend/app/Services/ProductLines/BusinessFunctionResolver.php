<?php

declare(strict_types=1);

namespace App\Services\ProductLines;

use App\Services\ProductCategories\CategoryHierarchy;

/**
 * Spec 0132: a `product_lines` row is now classified by CATEGORY alone — the
 * business function is no longer submitted, it is DERIVED from the category
 * (own, or inherited from its ancestors) and persisted verbatim into the
 * still-NOT-NULL `business_function_id` column every card table carries.
 *
 * This is the ONE place that derivation happens: both write channels
 * (ProductLineSetValidator, to check a row is even classifiable, and
 * ProductLineWriter, to persist what it resolves to) go through
 * `resolveMany()`, batched off CategoryHierarchy's own memoized projection —
 * never a query per row, whatever the collection's size.
 */
final class BusinessFunctionResolver
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * $categoryIds' EFFECTIVE business function id, or null when neither the
     * category nor any of its ancestors carries one (spec 0132 AC-003 — e.g.
     * a category caught in the known `parent_id` cycle data issue). A
     * category absent from the tree entirely resolves to null too: the
     * caller's own existence/selectability rule is what reports that case.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, int|null>
     */
    public function resolveMany(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $summaries = $this->hierarchy->effectiveBusinessFunctionSummaries();

        return array_reduce(
            $categoryIds,
            static function (array $resolved, int $categoryId) use ($summaries): array {
                $resolved[$categoryId] = $summaries[$categoryId]['id'] ?? null;

                return $resolved;
            },
            [],
        );
    }
}
