<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "Accreditamenti" branch of the catalogue (user directive 2026-10-20,
 * "ACCREDITAMENTI_PARTNERSHIP SEDI" list): a root of its own, named after the
 * list's "Gruppo di lavoro" and linked to the "Accreditamenti" business
 * function of the organisation chart, whose single subcategory is the legacy
 * "PARTNERSHIP/SEDI-" root prefixed with the branch like "Istruzione"
 * ("Accreditamenti - PARTNERSHIP/SEDI"), a classification target hosting the
 * products of AccreditationProductCatalogue. Pure data, read by
 * BranchCatalogues (tree), CatalogProducts (products), CatalogRootRules and
 * QualificaBusinessFunctionLinkSeeder.
 *
 * Worked like "Consulenza", every product under the "Consulenza" typology,
 * with the default working states and no Commessa field. Like "Istruzione" it
 * has NO offer field and no offer form (user directive 2026-10-20).
 */
final class AccreditationCatalogue
{
    public const string ROOT = 'Accreditamenti';

    public const string BUSINESS_FUNCTION = 'Accreditamenti';

    public const string BRANCH_PARTNERSHIPS = 'Accreditamenti - PARTNERSHIP/SEDI';

    /**
     * Every subcategory is a classification target, never a container (spec
     * 0074), for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [
        self::BRANCH_PARTNERSHIPS,
    ];

    /**
     * The root's subcategories, for QualificaCatalogSeeder::CATALOG: each hosts
     * its products directly, so none has children.
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [
        self::BRANCH_PARTNERSHIPS => [],
    ];

    /**
     * Category name => the product typology its products are filed under, by
     * CODE (ProductTypologySeeder): "tipologia consulenza" for the whole list.
     *
     * @var array<string, string>
     */
    public const array TYPOLOGIES = [
        self::BRANCH_PARTNERSHIPS => 'consultancy',
    ];
}
