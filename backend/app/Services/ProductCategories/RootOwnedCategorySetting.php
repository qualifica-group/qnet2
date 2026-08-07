<?php

namespace App\Services\ProductCategories;

use App\Models\ProductCategory;

/**
 * Shared machinery of every ROOT-OWNED product-category setting
 * (`requires_quote`, `management_mode`, `single_quote_per_opportunity`).
 *
 * The semantics are identical for all of them: only a category without a
 * parent authors the value, every descendant carries the root's verbatim (no
 * per-branch override, unlike the business function of spec 0023, whose
 * nearest-ancestor semantics allow a mid-chain owner), and the value is
 * DENORMALISED onto every row rather than resolved by a walk at read time —
 * so the grid, filters, export and for-select paths all read a real column
 * with no query per row.
 *
 * That denormalisation is only safe while a subclass of this class is the
 * only writer of its column outside the root's own edit: `syncSubtree()` is
 * called by ProductCategoryService after any write that can disturb the
 * invariant (a value change, a reparent — including the bulk move, which
 * routes through the same update path).
 *
 * A subclass names its column and narrows the return type of the inherited
 * value; it adds no behaviour of its own.
 */
abstract class RootOwnedCategorySetting
{
    /**
     * Defensive cap on the root walk, mirroring CategoryHierarchy's: the
     * write-side anti-cycle guard makes a real cycle impossible, so this
     * only stops corrupted data from looping forever.
     */
    private const int MAX_DEPTH = 100;

    public function __construct(protected readonly CategoryHierarchy $hierarchy) {}

    /**
     * The root category $category takes its value FROM, for the read-only
     * display in the child's form/detail. Null when $category is itself a
     * root (it owns the value, nothing is inherited).
     *
     * @return array{id: int, name: string}|null
     */
    public function sourceCategoryFor(ProductCategory $category): ?array
    {
        $root = $this->rootOf($category->parent_id);

        return $root !== null ? ['id' => $root->id, 'name' => $root->name] : null;
    }

    /**
     * Re-aligns $category and its whole subtree on the effective value: the
     * branch root's, or $category's own when it IS the root. Idempotent —
     * rows already holding the value are left untouched (no pointless UPDATE,
     * no activity-log noise on the descendants).
     */
    public function syncSubtree(ProductCategory $category): void
    {
        $column = $this->column();
        $effective = $this->inheritedRawValueFor($category->parent_id) ?? $category->getAttribute($column);

        if ($category->getAttribute($column) !== $effective) {
            $category->update([$column => $effective]);
        }

        $descendantIds = $this->hierarchy->descendantIds($category->id);

        if ($descendantIds !== []) {
            ProductCategory::whereIn('id', $descendantIds)
                ->where($column, '!=', $effective)
                ->update([$column => $effective]);
        }
    }

    /** The `product_categories` column this setting lives on. */
    abstract protected function column(): string;

    /**
     * The CAST value a child of $parentId inherits — its branch root's. Null
     * means "nothing to inherit": $parentId is null (the category is a root
     * and owns its own value) or the chain is broken.
     */
    protected function inheritedRawValueFor(?int $parentId): mixed
    {
        return $this->rootOf($parentId)?->getAttribute($this->column());
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
