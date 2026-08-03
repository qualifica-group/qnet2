<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;

/**
 * Builds coherent (product_category_id, business_function_id) pairs for the
 * demo seeders (spec 0023 REV): the business function is DERIVED from each
 * category's EFFECTIVE one (own or inherited), so the demo data never pairs a
 * category with a mismatched function — the same invariant the FormRequests now
 * reject. Categories with no effective business function are dropped (they
 * cannot form a valid required pair on a standalone Project/Campaign).
 *
 * Unselectable categories are dropped too (spec 0074, user directive
 * 2026-08-03): a container is not a classification target, App\Rules\
 * SelectableProductCategory rejects it on Project/Campaign, and the pair is
 * what LeadOpportunityDefaultsResolver turns into the converted opportunity's
 * product line — so a container here would surface as an unselectable line on
 * a seeded deal.
 */
trait ResolvesCategoryBusinessFunction
{
    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @return Collection<int, array{product_category_id: int, business_function_id: int}>
     */
    protected function coherentClassificationPairs(Collection $categories): Collection
    {
        $summaries = app(CategoryHierarchy::class)->effectiveBusinessFunctionSummaries();

        return $categories
            ->filter(static fn (ProductCategory $category): bool => (bool) $category->is_selectable)
            ->map(static fn (ProductCategory $category): array => [
                'product_category_id' => $category->id,
                'business_function_id' => $summaries[$category->id]['id'] ?? null,
            ])
            ->filter(static fn (array $pair): bool => $pair['business_function_id'] !== null)
            ->values();
    }
}
