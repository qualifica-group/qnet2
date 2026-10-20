<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "Istruzione" branch of the catalogue (user directive 2026-10-20,
 * "ISTRUZIONE" list): a root of its own, linked to the "Istruzione" business
 * function of the organisation chart, whose subcategories are the list's
 * "Categoria" column prefixed with the branch like "Sicurezza"
 * ("Istruzione - ..."; the products the list files on the root itself go on
 * "Istruzione - ISTRUZIONE", like "Finanza Agevolata - FINANZA AGEVOLATA"),
 * each a classification target hosting its products
 * (EducationProductCatalogue). Pure data, read by QualificaCatalogSeeder
 * (tree), CatalogProducts (products), CatalogRootRules and
 * QualificaBusinessFunctionLinkSeeder.
 *
 * Worked like "Consulenza", every product under the "Consulenza" typology,
 * with the default working states and no Commessa field. Unlike the other
 * branches it has NO offer field and no offer form (user directive
 * 2026-10-20, "niente campi flessibili").
 */
final class EducationCatalogue
{
    public const string ROOT = 'Istruzione';

    public const string BUSINESS_FUNCTION = 'Istruzione';

    public const string EDUCATION = 'Istruzione - ISTRUZIONE';

    public const string PNRR = 'Istruzione - PNRR';

    public const string SCHOOL_CONSULTING = 'Istruzione - Consulenza Scuola';

    public const string THERMAL_ACCOUNT = 'Istruzione - Conto Termico 3.0';

    /**
     * Every subcategory is a classification target, never a container (spec
     * 0074), for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [
        self::EDUCATION,
        self::PNRR,
        self::SCHOOL_CONSULTING,
        self::THERMAL_ACCOUNT,
    ];

    /**
     * The root's subcategories, for QualificaCatalogSeeder::CATALOG: each hosts
     * its products directly, so none has children.
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [
        self::EDUCATION => [],
        self::PNRR => [],
        self::SCHOOL_CONSULTING => [],
        self::THERMAL_ACCOUNT => [],
    ];

    /**
     * Category name => the product typology its products are filed under, by
     * CODE (ProductTypologySeeder): "tipologia consulenza" for the whole list.
     *
     * @var array<string, string>
     */
    public const array TYPOLOGIES = [
        self::EDUCATION => 'consultancy',
        self::PNRR => 'consultancy',
        self::SCHOOL_CONSULTING => 'consultancy',
        self::THERMAL_ACCOUNT => 'consultancy',
    ];
}
