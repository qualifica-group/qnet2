<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The products of the "Accreditamenti" branch, transcribed from the
 * "ACCREDITAMENTI_PARTNERSHIP SEDI" list (user directive 2026-10-20): its one
 * row, with its "Codice", its price (0), its "Mesi scadenza" (0) and no
 * description or supplier. Pure data, seeded by BranchProducts with the 22%
 * VAT rate and the "Consulenza" typology (AccreditationCatalogue::TYPOLOGIES).
 *
 * Names are the list's titles verbatim.
 */
final class AccreditationProductCatalogue
{
    /**
     * Category name => product name => its code, price, supplier and "Mesi scadenza".
     *
     * @var array<string, array<string, array{code?: string, price: float, supplier: string|null, expiration_months: int|null}>>
     */
    public const array PRODUCTS = [
        AccreditationCatalogue::BRANCH_PARTNERSHIPS => [
            'partnerships-sedi' => ['code' => 'GOL', 'price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
    ];
}
