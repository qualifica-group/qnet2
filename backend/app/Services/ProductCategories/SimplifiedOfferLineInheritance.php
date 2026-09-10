<?php

namespace App\Services\ProductCategories;

/**
 * Single authority on the `simplified_offer_line` flag of the
 * product-category tree (spec 0114): when true, an offer line written in
 * Gestione Richieste under this branch drops the quantity/unit-price/VAT
 * controls — the operator only picks the product, and the server freezes
 * those three values from it. Root-owned and subtree-mirrored: see
 * RootOwnedCategorySetting for the semantics, the rationale of the
 * denormalisation and the sync contract.
 *
 * The flag governs the OFFER-LINE UI/write-shape in Gestione Richieste only
 * (D-3): the Offerte module never reads it and keeps writing complete rows
 * regardless. It only shares its inheritance shape with
 * ContractGenerationInheritance and the other root-owned settings.
 */
final class SimplifiedOfferLineInheritance extends RootOwnedCategorySetting
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
        return 'simplified_offer_line';
    }
}
