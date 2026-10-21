<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The catalogue branches that come with their own product list, each a root
 * of its own whose subcategories are all classification targets: "SOA"
 * (user directive 2026-10-15, its Commessa set in QualificaBranchWorkOrderSeeder),
 * "Sicurezza" (2026-10-15), "Gare", "GDPR", "Finanza Agevolata" and
 * "Istruzione" (2026-10-20), "Accreditamenti" (2026-10-20), "ISO" (2026-10-21,
 * its Commessa set in QualificaBranchWorkOrderSeeder too). Split out of
 * QualificaCatalogSeeder (which reached its size limit, engineering.md §6):
 * the seeder reads the tree and the targets from here, in this order, which
 * is the order the roots are created in.
 *
 * Each branch's own class holds its offer fields (if any), typologies and
 * business function; its products are filed by CatalogProducts.
 */
final class BranchCatalogues
{
    /**
     * Root name => its subcategories, for QualificaCatalogSeeder::CATALOG.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const array TREE = [
        SoaCatalogue::ROOT => SoaCatalogue::SUBCATEGORIES,
        SafetyCatalogue::ROOT => SafetyCatalogue::SUBCATEGORIES,
        TenderCatalogue::ROOT => TenderCatalogue::SUBCATEGORIES,
        GdprCatalogue::ROOT => GdprCatalogue::SUBCATEGORIES,
        SubsidisedFinanceCatalogue::ROOT => SubsidisedFinanceCatalogue::SUBCATEGORIES,
        EducationCatalogue::ROOT => EducationCatalogue::SUBCATEGORIES,
        AccreditationCatalogue::ROOT => AccreditationCatalogue::SUBCATEGORIES,
        IsoCatalogue::ROOT => IsoSubcategories::SUBCATEGORIES,
    ];

    /**
     * Every subcategory of TREE, for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [
        ...SoaCatalogue::TARGET_CATEGORIES,
        ...SafetyCatalogue::TARGET_CATEGORIES,
        ...TenderCatalogue::TARGET_CATEGORIES,
        ...GdprCatalogue::TARGET_CATEGORIES,
        ...SubsidisedFinanceCatalogue::TARGET_CATEGORIES,
        ...EducationCatalogue::TARGET_CATEGORIES,
        ...AccreditationCatalogue::TARGET_CATEGORIES,
        ...IsoSubcategories::TARGET_CATEGORIES,
    ];
}
