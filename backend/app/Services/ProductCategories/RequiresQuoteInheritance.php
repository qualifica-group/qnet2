<?php

namespace App\Services\ProductCategories;

/**
 * Single authority on the `requires_quote` flag of the product-category tree.
 * Root-owned and subtree-mirrored: see RootOwnedCategorySetting for the
 * semantics, the rationale of the denormalisation and the sync contract.
 */
final class RequiresQuoteInheritance extends RootOwnedCategorySetting
{
    /**
     * The flag a child of $parentId INHERITS — its branch root's value. Null
     * means "nothing to inherit" (the category is a root and owns the flag).
     */
    public function inheritedValueFor(?int $parentId): ?bool
    {
        $inherited = $this->inheritedRawValueFor($parentId);

        return $inherited === null ? null : (bool) $inherited;
    }

    protected function column(): string
    {
        return 'requires_quote';
    }
}
