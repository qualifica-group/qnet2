<?php

namespace App\Services\ProductCategories;

/**
 * Single authority on the `single_quote_per_opportunity` flag of the
 * product-category tree (user directive 2026-08-07): when true, an
 * opportunity covered by the branch carries AT MOST ONE quote. Root-owned and
 * subtree-mirrored: see RootOwnedCategorySetting for the semantics, the
 * rationale of the denormalisation and the sync contract.
 *
 * The flag governs the NUMBER OF QUOTES an opportunity may carry, which is a
 * different rule from `management_mode` (how many product-category lines a
 * card — and rows an offer — may carry): a branch can legitimately want one
 * quote per opportunity while still allowing several product lines, and vice
 * versa. They only share an inheritance shape.
 */
final class SingleQuotePerOpportunityInheritance extends RootOwnedCategorySetting
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
        return 'single_quote_per_opportunity';
    }
}
