<?php

namespace App\Services\ProductCategories;

use App\Enums\CategoryManagementMode;

/**
 * Single authority on the `management_mode` value of the product-category
 * tree (spec 0077). Root-owned and subtree-mirrored: see
 * RootOwnedCategorySetting for the semantics, the rationale of the
 * denormalisation and the sync contract.
 */
final class CategoryManagementModeInheritance extends RootOwnedCategorySetting
{
    /**
     * The mode a child of $parentId INHERITS — its branch root's value. Null
     * means "nothing to inherit" (the category is a root and owns the mode).
     */
    public function inheritedValueFor(?int $parentId): ?CategoryManagementMode
    {
        $inherited = $this->inheritedRawValueFor($parentId);

        return $inherited instanceof CategoryManagementMode ? $inherited : null;
    }

    protected function column(): string
    {
        return 'management_mode';
    }
}
