<?php

namespace App\Services\ProductCategories;

use App\Models\ProductCategory;

/**
 * Single authority on the `requires_quote` flag of the product-category tree.
 *
 * The flag is OWNED BY THE ROOT of each branch: only a category without a
 * parent authors it, and every descendant carries the root's value verbatim
 * (no per-branch override, unlike the business function of spec 0023, whose
 * nearest-ancestor semantics allow a mid-chain owner).
 *
 * The value is DENORMALISED onto every row rather than resolved by a walk at
 * read time, so the grid, filters, export and for-select paths all read a
 * real column with no query per row. That denormalisation is only safe while
 * this class is the only writer of the column outside the root's own edit:
 * `syncSubtree()` is called by ProductCategoryService after any write that
 * can disturb the invariant (a flag change, a reparent — including the bulk
 * move, which routes through the same update path).
 *
 * Lives in its own class rather than on CategoryHierarchy, which is already
 * near the 500-line hard limit (engineering.md §6).
 */
final class RequiresQuoteInheritance
{
    /**
     * Defensive cap on the root walk, mirroring CategoryHierarchy's: the
     * write-side anti-cycle guard makes a real cycle impossible, so this
     * only stops corrupted data from looping forever.
     */
    private const int MAX_DEPTH = 100;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The flag a child of $parentId INHERITS — its branch root's value.
     * Null means "nothing to inherit": $parentId is null (the category is a
     * root and owns its own flag) or the chain is broken.
     */
    public function inheritedValueFor(?int $parentId): ?bool
    {
        $root = $this->rootOf($parentId);

        return $root !== null ? (bool) $root->requires_quote : null;
    }

    /**
     * The root category $category inherits the flag FROM, for the read-only
     * display in the child's form/detail. Null when $category is itself a
     * root (it owns the flag, nothing is inherited).
     *
     * @return array{id: int, name: string}|null
     */
    public function sourceCategoryFor(ProductCategory $category): ?array
    {
        $root = $this->rootOf($category->parent_id);

        return $root !== null ? ['id' => $root->id, 'name' => $root->name] : null;
    }

    /**
     * Re-aligns $category and its whole subtree on the effective flag: the
     * branch root's value, or $category's own when it IS the root. Idempotent
     * — rows already holding the value are left untouched (no pointless
     * UPDATE, no activity-log noise on the descendants).
     */
    public function syncSubtree(ProductCategory $category): void
    {
        $effective = $this->inheritedValueFor($category->parent_id) ?? (bool) $category->requires_quote;

        if ((bool) $category->requires_quote !== $effective) {
            $category->update(['requires_quote' => $effective]);
        }

        $descendantIds = $this->hierarchy->descendantIds($category->id);

        if ($descendantIds !== []) {
            ProductCategory::whereIn('id', $descendantIds)
                ->where('requires_quote', '!=', $effective)
                ->update(['requires_quote' => $effective]);
        }
    }

    /**
     * The root of the branch $categoryId belongs to ($categoryId itself when
     * it has no parent). Null when $categoryId is null or the row is gone.
     */
    private function rootOf(?int $categoryId): ?ProductCategory
    {
        if ($categoryId === null) {
            return null;
        }

        $node = ProductCategory::find($categoryId);
        $depth = 0;

        while ($node !== null && $node->parent_id !== null && $depth < self::MAX_DEPTH) {
            $node = ProductCategory::find($node->parent_id);
            $depth++;
        }

        return $node;
    }
}
