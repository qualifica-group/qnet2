<?php

namespace App\Services\ProductCategories;

/**
 * Single authority on the `request_offer_hidden` flag of the product-category
 * tree (spec 0414): when true, Gestione Richieste does not show the offer
 * section for the branch (a front-end visibility rule, not enforced by the
 * server). Root-owned and subtree-mirrored: see RootOwnedCategorySetting for
 * the semantics, the rationale of the denormalisation and the sync contract.
 */
final class RequestOfferHiddenInheritance extends RootOwnedCategorySetting
{
    /**
     * The flag a child of $parentId INHERITS, its branch root's value. Null
     * means "nothing to inherit" (the category is a root and owns the flag).
     */
    public function inheritedValueFor(?int $parentId): ?bool
    {
        $inherited = $this->inheritedRawValueFor($parentId);

        return $inherited === null ? null : (bool) $inherited;
    }

    protected function column(): string
    {
        return 'request_offer_hidden';
    }
}
