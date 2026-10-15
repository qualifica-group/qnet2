<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The "Sicurezza" branch of the catalogue (user directive 2026-10-15,
 * "Sicurezza 81_08" sheet and "SICUREZZA 81_09" list): a root of its own,
 * linked to the "Sicurezza" business function, whose subcategories are the
 * list's "Categoria" column prefixed with the branch ("Sicurezza - ...", user
 * directive 2026-10-15, see CatalogCategoryRenames) — every one a
 * classification target hosting its products (SafetyProductCatalogue). Pure data, read by QualificaCatalogSeeder
 * (tree and offer fields), QualificaQuoteLayoutSeeder (offer form),
 * CatalogProducts (products), CatalogRootRules and
 * QualificaBusinessFunctionLinkSeeder.
 *
 * Worked like "Consulenza" and SOA, every product under the "Consulenza"
 * typology, with the default working states and no Commessa field (user
 * decisions 2026-10-15). The offer fields sit on the root, inherited by every
 * subcategory with the root's form.
 *
 * `code` is the English identifier; `name` and the option labels are the
 * user-facing values, kept in their original language.
 */
final class SafetyCatalogue
{
    public const string ROOT = 'Sicurezza';

    public const string BUSINESS_FUNCTION = 'Sicurezza';

    public const string TRAINING_COURSES = 'Sicurezza - Corsi di formazione';

    public const string DOCUMENTS = 'Sicurezza - Documenti';

    public const string MEDICAL_VISITS = 'Sicurezza - Visite mediche';

    public const string SITE_INSPECTIONS = 'Sicurezza - Sopralluogo';

    public const string TRAVEL = 'Sicurezza - Trasferta';

    /**
     * Every subcategory is a classification target, never a container (spec
     * 0074), for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [
        self::TRAINING_COURSES,
        self::DOCUMENTS,
        self::MEDICAL_VISITS,
        self::SITE_INSPECTIONS,
        self::TRAVEL,
    ];

    /**
     * The root's subcategories, for QualificaCatalogSeeder::CATALOG: each hosts
     * its products directly, so none has children.
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [
        self::TRAINING_COURSES => [],
        self::DOCUMENTS => [],
        self::MEDICAL_VISITS => [],
        self::SITE_INSPECTIONS => [],
        self::TRAVEL => [],
    ];

    /**
     * Category name => the product typology its products are filed under, by
     * CODE (ProductTypologySeeder): "tipologia consulenza" for the whole list.
     *
     * @var array<string, string>
     */
    public const array TYPOLOGIES = [
        self::TRAINING_COURSES => 'consultancy',
        self::DOCUMENTS => 'consultancy',
        self::MEDICAL_VISITS => 'consultancy',
        self::SITE_INSPECTIONS => 'consultancy',
        self::TRAVEL => 'consultancy',
    ];

    /**
     * The suppliers the list's notes name: the F-gas products and the welding
     * ones. A product is linked only when a supplier registry of that name
     * exists (BranchProducts); "ND CONTROL" has no registry in the legacy yet.
     */
    public const string GONDOLA = 'GONDOLA SRLS';

    public const string ND_CONTROL = 'ND CONTROL';

    /**
     * The OFFERTA-context fields, in the sheet's order: the request data, then
     * the welder licences block.
     *
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>}>
     */
    public const array QUOTE_ATTRIBUTES = [
        ['code' => 'safety_company_report', 'name' => 'Visura camerale ordinaria', 'type' => 'boolean'],
        ['code' => 'safety_employee_count', 'name' => 'N° dipendenti', 'type' => 'integer'],
        ['code' => 'safety_requested_course_types', 'name' => 'Tipologia di corsi richiesti', 'type' => 'textarea'],
        ['code' => 'safety_course_count_per_type', 'name' => 'Numero di corsi richiesti per ogni tipologia', 'type' => 'textarea'],
        ['code' => 'safety_shifts_roles_workplaces', 'name' => 'Turni lavorativi, mansioni e luoghi di lavoro dei dipendenti', 'type' => 'textarea'],
        ['code' => 'safety_active_sites_count', 'name' => 'N° cantieri attivi', 'type' => 'integer'],
        ['code' => 'safety_company_sites_count', 'name' => 'N° sedi azienda', 'type' => 'integer'],
        ['code' => 'safety_welder_licences_count', 'name' => 'N° patentini saldatori richiesti', 'type' => 'integer'],
        ['code' => 'safety_welding_method', 'name' => 'Saldatura ad elettrodi o a filo', 'type' => 'enum', 'options' => [
            ['value' => 'electrode', 'label' => 'Ad elettrodi'],
            ['value' => 'wire', 'label' => 'A filo'],
            ['value' => 'both', 'label' => 'Entrambe'],
        ]],
        ['code' => 'safety_weld_joint', 'name' => 'Saldatura a testa o ad angolo', 'type' => 'enum', 'options' => [
            ['value' => 'butt', 'label' => 'A testa'],
            ['value' => 'fillet', 'label' => 'Ad angolo'],
            ['value' => 'both', 'label' => 'Entrambe'],
        ]],
        ['code' => 'safety_weld_thickness', 'name' => 'Spessore minimo e massimo saldato', 'type' => 'text'],
        ['code' => 'safety_weld_material', 'name' => 'Materiale saldato (ferro/tubo/piastre)', 'type' => 'text'],
    ];

    /**
     * The offer form: the sheet's two blocks.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array QUOTE_SECTIONS = [
        ['safety-request', 'Sicurezza 81/08', [
            ['safety_company_report', 'safety_employee_count'],
            ['safety_requested_course_types'],
            ['safety_course_count_per_type'],
            ['safety_shifts_roles_workplaces'],
            ['safety_active_sites_count', 'safety_company_sites_count'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Dati dell\'azienda e corsi richiesti.']],
        ['safety-welder-licences', 'Patentino saldatori', [
            ['safety_welder_licences_count'],
            ['safety_welding_method', 'safety_weld_joint'],
            ['safety_weld_thickness', 'safety_weld_material'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Descrizione patentini: tipo di saldatura, spessori e materiale.']],
    ];
}
