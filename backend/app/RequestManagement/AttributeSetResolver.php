<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;

/**
 * The "applicable attributes" set for a set of product categories, in one
 * usage context (spec 0084, D-4): the UNION, deduped by `code`, of the
 * EFFECTIVE (own+inherited) $context attributes of every category in
 * $categoryIds (App\Services\ProductCategories\CategoryHierarchy::
 * effectiveAttributes()). When the same `code` is required by at least one
 * category, the merged descriptor is required (the strictest requirement
 * wins); its position stays where it FIRST appeared while merging, then the
 * whole set is reordered by sort_order/code for a stable, deterministic
 * response.
 *
 * Generalized from the former Opportunity-only `ApplicableAttributesResolver`
 * (spec 0049): the caller now supplies the category ids directly instead of
 * this class deriving them from an Opportunity's product lines, so it serves
 * every context — Product (App\Products\ProductAttributeResolver, one
 * category) and Quote (App\Quotes\QuoteAttributeResolver, many) alike — with
 * no per-domain duplication (constraints: no clone of this resolution logic).
 */
final class AttributeSetResolver
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * @param  array<int, int>  $categoryIds  distinct or not, any order — deduped and order-preserved internally
     * @return Collection<int, ApplicableAttribute>
     */
    public function resolve(array $categoryIds, AttributeContext $context): Collection
    {
        $categories = $this->loadCategories($categoryIds);

        if ($categories->isEmpty()) {
            return collect();
        }

        $merged = $this->mergeByCode($categories, $context);

        // Stable order — sort_order then code.
        return $merged->values()
            ->sort(fn (ApplicableAttribute $a, ApplicableAttribute $b): int => [$a->sortOrder, $a->code] <=> [$b->sortOrder, $b->code])
            ->values();
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return Collection<int, ProductCategory>
     */
    private function loadCategories(array $categoryIds): Collection
    {
        $byId = ProductCategory::query()->whereIn('id', $categoryIds)->get()->keyBy('id');

        return collect($categoryIds)->unique()->map(fn (int $id): ?ProductCategory => $byId->get($id))
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @return Collection<string, ApplicableAttribute> keyed by `code`
     */
    private function mergeByCode(Collection $categories, AttributeContext $context): Collection
    {
        $merged = collect();

        foreach ($categories as $category) {
            foreach ($this->hierarchy->effectiveAttributes($category, $context) as $row) {
                $this->mergeOne($merged, ApplicableAttribute::fromEffectiveAttributeRow($row));
            }
        }

        return $merged;
    }

    /**
     * @param  Collection<string, ApplicableAttribute>  $merged
     */
    private function mergeOne(Collection $merged, ApplicableAttribute $attribute): void
    {
        $existing = $merged->get($attribute->code);

        if ($existing === null) {
            $merged->put($attribute->code, $attribute);

            return;
        }

        if ($attribute->isRequired && ! $existing->isRequired) {
            $merged->put($attribute->code, $existing->withRequired(true));
        }
    }
}
