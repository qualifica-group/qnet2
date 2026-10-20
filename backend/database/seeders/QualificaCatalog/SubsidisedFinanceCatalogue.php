<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The "Finanza Agevolata" branch of the catalogue (user directive 2026-10-20,
 * "Gestione richieste finanza Agevolata" sheet and "FINANZA AGEVOLATA" list): a
 * root of its own, linked to the "Finanza Agevolata" business function, whose
 * thirteen subcategories are the sheet's product lines prefixed with the branch
 * like "Sicurezza" ("Finanza Agevolata - ..."), each a classification target
 * hosting its products (SubsidisedFinanceProductCatalogue). Pure data, read by
 * QualificaCatalogSeeder (tree and offer fields), QualificaQuoteLayoutSeeder
 * (offer form), CatalogProducts (products), CatalogRootRules and
 * QualificaBusinessFunctionLinkSeeder.
 *
 * Worked like "Consulenza", every product under the "Consulenza" typology,
 * with the default working states and no Commessa field. The offer fields sit
 * on the root, inherited by every line with the root's form.
 *
 * `code` is the English identifier; `name` is the user-facing label, kept in
 * its original language.
 */
final class SubsidisedFinanceCatalogue
{
    public const string ROOT = 'Finanza Agevolata';

    public const string BUSINESS_FUNCTION = 'Finanza Agevolata';

    public const string SELF_EMPLOYMENT_CENTRE_NORTH = 'Finanza Agevolata - AUT C.NORD';

    public const string TOURISM_DECREE = 'Finanza Agevolata - Decreto Turismo';

    public const string SUBSIDISED_FINANCE = 'Finanza Agevolata - FINANZA AGEVOLATA';

    public const string FNC = 'Finanza Agevolata - FNC';

    public const string FUNDS = 'Finanza Agevolata - FONDI';

    public const string CONTINUING_TRAINING = 'Finanza Agevolata - FORMAZIONE CONTINUA';

    public const string FRC = 'Finanza Agevolata - FRC';

    public const string FUNT = 'Finanza Agevolata - FUNT';

    public const string HIRING_INCENTIVES = 'Finanza Agevolata - INCENTIVI ASSUNZIONI';

    public const string INDUSTRY_4_0 = 'Finanza Agevolata - Industria 4.0';

    public const string HYPER_DEPRECIATION = 'Finanza Agevolata - IPERAMMORTAMENTO';

    public const string ISI_INAIL = 'Finanza Agevolata - ISI INAIL';

    public const string RESTO_AL_SUD = 'Finanza Agevolata - RESTO AL SUD';

    /**
     * Every subcategory is a classification target, never a container (spec
     * 0074), for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [
        self::SELF_EMPLOYMENT_CENTRE_NORTH,
        self::TOURISM_DECREE,
        self::SUBSIDISED_FINANCE,
        self::FNC,
        self::FUNDS,
        self::CONTINUING_TRAINING,
        self::FRC,
        self::FUNT,
        self::HIRING_INCENTIVES,
        self::INDUSTRY_4_0,
        self::HYPER_DEPRECIATION,
        self::ISI_INAIL,
        self::RESTO_AL_SUD,
    ];

    /**
     * The root's subcategories, for QualificaCatalogSeeder::CATALOG: each hosts
     * its products directly, so none has children.
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [
        self::SELF_EMPLOYMENT_CENTRE_NORTH => [],
        self::TOURISM_DECREE => [],
        self::SUBSIDISED_FINANCE => [],
        self::FNC => [],
        self::FUNDS => [],
        self::CONTINUING_TRAINING => [],
        self::FRC => [],
        self::FUNT => [],
        self::HIRING_INCENTIVES => [],
        self::INDUSTRY_4_0 => [],
        self::HYPER_DEPRECIATION => [],
        self::ISI_INAIL => [],
        self::RESTO_AL_SUD => [],
    ];

    /**
     * Category name => the product typology its products are filed under, by
     * CODE (ProductTypologySeeder): "tipologia consulenza" for the whole list.
     *
     * @var array<string, string>
     */
    public const array TYPOLOGIES = [
        self::SELF_EMPLOYMENT_CENTRE_NORTH => 'consultancy',
        self::TOURISM_DECREE => 'consultancy',
        self::SUBSIDISED_FINANCE => 'consultancy',
        self::FNC => 'consultancy',
        self::FUNDS => 'consultancy',
        self::CONTINUING_TRAINING => 'consultancy',
        self::FRC => 'consultancy',
        self::FUNT => 'consultancy',
        self::HIRING_INCENTIVES => 'consultancy',
        self::INDUSTRY_4_0 => 'consultancy',
        self::HYPER_DEPRECIATION => 'consultancy',
        self::ISI_INAIL => 'consultancy',
        self::RESTO_AL_SUD => 'consultancy',
    ];

    /**
     * The OFFERTA-context fields, in the sheet's order ("Informazioni
     * Flessibili"): the check-list to attach is a flag, like the documents of
     * the "Gare" branch.
     *
     * @var list<array{code: string, name: string, type: string}>
     */
    public const array QUOTE_ATTRIBUTES = [
        ['code' => 'subsidised_finance_requested_measure', 'name' => 'Misura richiesta', 'type' => 'text'],
        ['code' => 'subsidised_finance_checklist', 'name' => 'Check-list da allegare', 'type' => 'boolean'],
        ['code' => 'subsidised_finance_notes', 'name' => 'Note', 'type' => 'textarea'],
    ];

    /**
     * The offer form: the sheet's single block.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array QUOTE_SECTIONS = [
        ['subsidised-finance-request', 'Finanza Agevolata', [
            ['subsidised_finance_requested_measure', 'subsidised_finance_checklist'],
            ['subsidised_finance_notes'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Misura richiesta e documenti della richiesta.']],
    ];
}
