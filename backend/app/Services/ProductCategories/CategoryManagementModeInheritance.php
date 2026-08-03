<?php

namespace App\Services\ProductCategories;

use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;

/**
 * Single authority on the `management_mode` flag of the product-category
 * tree (spec 0077). Structural twin of RequiresQuoteInheritance — same
 * root-owned, subtree-mirrored semantics and the same rationale for the
 * denormalisation (see that class's docblock); kept as its own class rather
 * than merged with it because the two flags are independent business rules
 * that only happen to share an inheritance shape (constraints: "nessun nuovo
 * pattern di ereditarietà").
 */
final class CategoryManagementModeInheritance
{
    /**
     * Defensive cap on the root walk, mirroring CategoryHierarchy's: the
     * write-side anti-cycle guard makes a real cycle impossible, so this
     * only stops corrupted data from looping forever.
     */
    private const int MAX_DEPTH = 100;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The mode a child of $parentId INHERITS — its branch root's value.
     * Null means "nothing to inherit": $parentId is null (the category is a
     * root and owns its own mode) or the chain is broken.
     */
    public function inheritedValueFor(?int $parentId): ?CategoryManagementMode
    {
        return $this->rootOf($parentId)?->management_mode;
    }

    /**
     * The root category $category inherits the mode FROM, for the read-only
     * display in the child's form/detail. Null when $category is itself a
     * root (it owns the mode, nothing is inherited).
     *
     * @return array{id: int, name: string}|null
     */
    public function sourceCategoryFor(ProductCategory $category): ?array
    {
        $root = $this->rootOf($category->parent_id);

        return $root !== null ? ['id' => $root->id, 'name' => $root->name] : null;
    }

    /**
     * Re-aligns $category and its whole subtree on the effective mode: the
     * branch root's value, or $category's own when it IS the root. Idempotent
     * — rows already holding the value are left untouched (no pointless
     * UPDATE, no activity-log noise on the descendants).
     */
    public function syncSubtree(ProductCategory $category): void
    {
        $effective = $this->inheritedValueFor($category->parent_id) ?? $category->management_mode;

        if ($category->management_mode !== $effective) {
            $category->update(['management_mode' => $effective]);
        }

        $descendantIds = $this->hierarchy->descendantIds($category->id);

        if ($descendantIds !== []) {
            ProductCategory::whereIn('id', $descendantIds)
                ->where('management_mode', '!=', $effective)
                ->update(['management_mode' => $effective]);
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
