<?php

namespace App\Services\ProductCategories;

/**
 * Single authority on the `multiple_open_opportunities` flag of the
 * product-category tree (user request 2026-11-11): when true, an anagrafica
 * may carry several open opportunities of the branch at once
 * (RegistryOpenOpportunityGuard). Root-owned and subtree-mirrored: see
 * RootOwnedCategorySetting for the semantics, the rationale of the
 * denormalisation and the sync contract.
 *
 * Distinct from `management_mode`: a `single` branch is already exempt from
 * the block, but also caps the card and the offer at one row; this flag lifts
 * the block on a `multiple` branch, whose offers keep several rows.
 */
final class MultipleOpenOpportunitiesInheritance extends RootOwnedCategorySetting
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
        return 'multiple_open_opportunities';
    }
}
