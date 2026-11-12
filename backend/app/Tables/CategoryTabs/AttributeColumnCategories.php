<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs;

use App\Models\ProductCategory;
use App\Tables\Shared\LinkedCategoryGroups;
use App\Tables\Shared\PresetListFilters;
use Illuminate\Support\Facades\Auth;

/**
 * The categories whose flexible fields a grid shows as `attr.<code>` columns,
 * deduced from the advanced filter values (spec 0374, D-1, D-9, D-10).
 *
 * The selection is `product_category`, else the effective roots of
 * `parent_category` (PresetListFilters::effectiveRootIds(): an empty value is
 * every root of a limited actor, user directive 2026-11-12). In the parent
 * category a root stands for its whole group (D-12). In the product category one
 * category is taken as is; several only when they all belong to the same linked group: a
 * current root plus the legacy roots pointing at it (`current_category_id`),
 * every one still a root. Anything else shows no column.
 */
final class AttributeColumnCategories
{
    public function __construct(
        private readonly LinkedCategoryGroups $groups,
        private readonly PresetListFilters $presets,
    ) {}

    /**
     * @param  array<string, mixed>  $values  advanced filter values by name
     * @return array<int, int> the current category first, then the legacy ones by id; [] = no column
     */
    public function fromFilters(array $values): array
    {
        $products = array_values(array_unique(PresetListFilters::ids($values[PresetListFilters::PRODUCT_CATEGORY] ?? null)));
        sort($products);

        if ($products !== []) {
            return count($products) === 1 ? $products : $this->linkedGroup($products);
        }

        // A request without the parent category (a grid inside a record) shows no column.
        if (! array_key_exists(PresetListFilters::PARENT_CATEGORY, $values)) {
            return [];
        }

        $roots = $this->presets->effectiveRootIds($values[PresetListFilters::PARENT_CATEGORY], Auth::user());

        return $roots === null ? [] : $this->parentGroup($roots);
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
