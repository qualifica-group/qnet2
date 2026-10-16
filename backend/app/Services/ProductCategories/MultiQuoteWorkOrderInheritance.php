<?php

namespace App\Services\ProductCategories;

/**
 * Single authority on the `multi_quote_work_order` flag of the
 * product-category tree (spec 0267): when true, revenue lines of the branch
 * coming from different quotes/contracts can be scheduled into ONE work
 * order. Root-owned and subtree-mirrored: see RootOwnedCategorySetting for
 * the semantics, the rationale of the denormalisation and the sync contract.
 */
final class MultiQuoteWorkOrderInheritance extends RootOwnedCategorySetting
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
        return 'multi_quote_work_order';
    }
}
