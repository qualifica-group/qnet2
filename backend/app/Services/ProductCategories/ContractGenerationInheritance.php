<?php

namespace App\Services\ProductCategories;

/**
 * Single authority on the `generates_contract` flag of the product-category
 * tree (spec 0091): when false, an offer covered by the branch that reaches a
 * positively-closed working status opens NO contract — the deal never shows up
 * in the Contratti module. Root-owned and subtree-mirrored: see
 * RootOwnedCategorySetting for the semantics, the rationale of the
 * denormalisation and the sync contract.
 *
 * The flag governs whether a CONTRACT EXISTS at all, which is a different
 * question from `requires_quote` (whether the branch is quoted upstream) and
 * from `single_quote_per_opportunity` (how many offer documents an opportunity
 * may carry). They only share an inheritance shape.
 */
final class ContractGenerationInheritance extends RootOwnedCategorySetting
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
        return 'generates_contract';
    }
}
