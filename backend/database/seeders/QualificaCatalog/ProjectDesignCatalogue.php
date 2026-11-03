<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "Progettazione" branch of the catalogue (user directive 2026-11-03,
 * "Funzione Aziendale_ Progettazione" list): a root of its own, linked to the
 * "Progettazione" business function of the organisation chart, whose single
 * subcategory "Progettazione interna" is a classification target hosting the
 * products of ProjectDesignProductCatalogue. Its name already carries the
 * branch, so it takes no "Progettazione - " prefix. Pure data, read by
 * BranchCatalogues (tree), CatalogProducts (products), CatalogRootRules,
 * QualificaBusinessFunctionLinkSeeder and QualificaProjectDesignCatalogSeeder,
 * the standalone entry point for an installation already seeded.
 *
 * Worked like "Consulenza", every product under the "Consulenza" typology,
 * with the default working states, no offer field and no Commessa field.
 */
final class ProjectDesignCatalogue
{
    public const string ROOT = 'Progettazione';

    public const string BUSINESS_FUNCTION = 'Progettazione';

    public const string INTERNAL = 'Progettazione interna';

    /**
     * Every subcategory is a classification target, never a container (spec
     * 0074), for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [
        self::INTERNAL,
    ];

    /**
     * The root's subcategories, for QualificaCatalogSeeder::CATALOG: each hosts
     * its products directly, so none has children.
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [
        self::INTERNAL => [],
    ];

    /**
     * Category name => the product typology its products are filed under, by
     * CODE (ProductTypologySeeder): "tipologia consulenza" for the whole list.
     *
     * @var array<string, string>
     */
    public const array TYPOLOGIES = [
        self::INTERNAL => 'consultancy',
    ];
}
