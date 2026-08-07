<?php

namespace App\Services\ProductCategories;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * The nested-tree projection of `product_categories` (spec 0017 tree
 * endpoint), split out of CategoryHierarchy — which stays the authority on
 * the ancestor/attribute WALKS — once that class reached its size limit
 * (engineering.md §6). One query for the whole tree, assembled in PHP.
 *
 * Every ROOT-OWNED setting (`requires_quote`, `management_mode`,
 * `single_quote_per_opportunity`) travels on each node ALREADY EFFECTIVE: the
 * columns are denormalised onto every row (RootOwnedCategorySetting), so the
 * category form previews what a child would inherit from a candidate parent
 * by reading this cache, with no extra request and no walk here.
 */
final class CategoryTreeBuilder
{
    /**
     * The full category tree, roots first, each node carrying its own
     * attributes/products counts and its OWN business_function_id (spec 0023
     * REV — NOT the effective/inherited one: the frontend resolves that
     * inheritance itself by walking this cached tree's `parent_id` chain, so
     * the write-side no-override 422 stays the sole authority). No extra
     * query: `get()` below already hydrates the full row,
     * business_function_id included.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        $byParent = ProductCategory::query()
            ->withCount(['attributes', 'products'])
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        return $this->buildNodes($byParent, null);
    }

    /**
     * @param  Collection<int|string, Collection<int, ProductCategory>>  $byParent
     * @return array<int, array<string, mixed>>
     */
    private function buildNodes(Collection $byParent, ?int $parentId): array
    {
        $nodes = [];

        /** @var Collection<int, ProductCategory> $children */
        $children = $byParent->get($parentId ?? '', collect());

        foreach ($children as $category) {
            $nodes[] = [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'children' => $this->buildNodes($byParent, $category->id),
                'attributes_count' => (int) $category->attributes_count,
                'products_count' => (int) $category->products_count,
                'business_function_id' => $category->business_function_id,
                'requires_quote' => (bool) $category->requires_quote,
                // Spec 0074: the tree is the STRUCTURAL channel and stays
                // complete — unselectable nodes are still parents. The flag
                // travels with each node so the pickers built on this cache
                // (the product form's category picker) can filter themselves.
                'is_selectable' => (bool) $category->is_selectable,
                'management_mode' => $category->management_mode->value,
                'single_quote_per_opportunity' => (bool) $category->single_quote_per_opportunity,
            ];
        }

        return $nodes;
    }
}
