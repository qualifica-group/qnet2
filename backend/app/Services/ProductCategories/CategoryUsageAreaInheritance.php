<?php

namespace App\Services\ProductCategories;

use App\Enums\CategoryUsageArea;

/**
 * Single authority on the `usage_area` value of the product-category tree
 * (spec 0389). Root-owned and subtree-mirrored: see RootOwnedCategorySetting
 * for the semantics, the rationale of the denormalisation and the sync
 * contract.
 */
final class CategoryUsageAreaInheritance extends RootOwnedCategorySetting
{
    /**
     * The area a child of $parentId INHERITS — its branch root's value. Null
     * means "nothing to inherit" (the category is a root and owns the area).
     */
    public function inheritedValueFor(?int $parentId): ?CategoryUsageArea
    {
        $inherited = $this->inheritedRawValueFor($parentId);

        return $inherited instanceof CategoryUsageArea ? $inherited : null;
    }

    protected function column(): string
    {
        return 'usage_area';
    }
}
