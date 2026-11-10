<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs;

use App\Models\ProductCategory;
use App\Services\ProductCategories\ProductCategoryForSelectResolver;
use App\Tables\Shared\LinkedCategoryGroups;
use App\Tables\Shared\PresetListFilters;

/**
 * The categories whose flexible fields a grid shows as `attr.<code>` columns,
 * deduced from the advanced filter values (spec 0374, D-1, D-9, D-10).
 *
 * The selection is `product_category`, else `parent_category`. In the parent
 * category a root stands for its whole group (D-12). In the product category one
 * category is taken as is; several only when they all belong to the same linked group: a
 * current root plus the legacy roots pointing at it (`current_category_id`),
 * every one still a root. Anything else shows no column.
 */
final class AttributeColumnCategories
{
    public function __construct(private readonly LinkedCategoryGroups $groups) {}

    /**
     * @param  array<string, mixed>  $values  advanced filter values by name
     * @return array<int, int> the current category first, then the legacy ones by id; [] = no column
     */
    public function fromFilters(array $values): array
    {
        $products = PresetListFilters::ids($values[PresetListFilters::PRODUCT_CATEGORY] ?? null);
        $selection = $products !== [] ? $products : PresetListFilters::ids($values[PresetListFilters::PARENT_CATEGORY] ?? null);
        $selection = array_values(array_unique($selection));
        sort($selection);

        if ($selection === [] || in_array(ProductCategoryForSelectResolver::ALL_ROOTS_ID, $selection, true)) {
            return [];
        }

        if ($products === []) {
            return $this->parentGroup($selection);
        }

        return count($selection) === 1 ? $selection : $this->linkedGroup($selection);
    }

    /**
     * Parent category (spec 0374 D-12): a root stands for its whole group, a
     * linked legacy root for its current one; two different roots show nothing.
     *
     * @param  array<int, int>  $selection
     * @return array<int, int>
     */
    private function parentGroup(array $selection): array
    {
        $roots = $this->groups->currentRootIds($selection);

        return count($roots) === 1 ? $this->groups->withLinkedLegacy($roots) : [];
    }

    /**
     * @param  array<int, int>  $selection  sorted ids, two or more
     * @return array<int, int>
     */
    private function linkedGroup(array $selection): array
    {
        $roots = ProductCategory::query()
            ->whereIn('id', $selection)
            ->whereNull('parent_id')
            ->get(['id', 'is_legacy', 'current_category_id']);

        if ($roots->count() !== count($selection)) {
            return [];
        }

        $groups = $roots->map(static fn (ProductCategory $root): ?int => $root->is_legacy ? $root->current_category_id : $root->id);
        $current = $groups->first();

        if ($current === null || $groups->unique()->count() !== 1 || ! $this->isCurrentRoot($current)) {
            return [];
        }

        return [...array_filter([$current], static fn (int $id): bool => in_array($id, $selection, true)), ...array_values(array_diff($selection, [$current]))];
    }

    private function isCurrentRoot(int $id): bool
    {
        return ProductCategory::query()->whereKey($id)->whereNull('parent_id')->where('is_legacy', false)->exists();
    }
}
