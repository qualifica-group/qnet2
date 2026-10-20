<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The "Gare" branch of the catalogue (user directive 2026-10-20, "GESTIONE
 * RICHIESTE SETTORE GARE" sheet and "SERVIZI GARE" list): a root of its own,
 * linked to the "Gare" business function, whose subcategories are the sheet's
 * product lines prefixed with the branch like "Sicurezza" ("Gare - ..."), every
 * one a classification target hosting its products (TenderProductCatalogue).
 * Pure data, read by QualificaCatalogSeeder (tree and offer fields),
 * QualificaQuoteLayoutSeeder (offer forms), CatalogProducts (products),
 * CatalogRootRules and QualificaBusinessFunctionLinkSeeder.
 *
 * Worked like "Consulenza", every product under the "Consulenza" typology,
 * with the default working states and no Commessa field. The offer fields are
 * the sheet's "(DA ALLEGARE)" documents, one flag each, assigned on the
 * subcategories the sheet lists them for; the free-text sections it marks in
 * yellow are left out (user directive 2026-10-20).
 *
 * `code` is the English identifier; `name` is the user-facing label, kept in
 * its original language.
 */
final class TenderCatalogue
{
    public const string ROOT = 'Gare';

    public const string BUSINESS_FUNCTION = 'Gare';

    public const string SUPPLIER_REGISTERS = 'Gare - Albo Fornitori';

    public const string ENVIRONMENTAL_OPERATORS_REGISTER = 'Gare - Albo Gestori Ambientali';

    public const string ANAC = 'Gare - ANAC';

    public const string RELIANCE = 'Gare - Avvalimenti';

    public const string GRADE_CONSORTIUM = 'Gare - Consorzio Grade';

    public const string TRAINING_COURSE = 'Gare - Corso preparazione gare';

    public const string TENDERS = 'Gare - Gare';

    public const string TENDER_JOURNAL = 'Gare - Giornale Telematico';

    public const string MEPA = 'Gare - Mepa';

    public const string TECHNICAL_PROPOSAL = 'Gare - Progetto Tecnico';

    public const string JOINT_VENTURES = 'Gare - RTI/ATI';

    public const string WHITE_LIST = 'Gare - WHITE LIST';

    /**
     * Every subcategory is a classification target, never a container (spec
     * 0074), for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [
        self::SUPPLIER_REGISTERS,
        self::ENVIRONMENTAL_OPERATORS_REGISTER,
        self::ANAC,
        self::RELIANCE,
        self::GRADE_CONSORTIUM,
        self::TRAINING_COURSE,
        self::TENDERS,
        self::TENDER_JOURNAL,
        self::MEPA,
        self::TECHNICAL_PROPOSAL,
        self::JOINT_VENTURES,
        self::WHITE_LIST,
    ];

    /**
     * The root's subcategories, for QualificaCatalogSeeder::CATALOG: each hosts
     * its products directly, so none has children.
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [
        self::SUPPLIER_REGISTERS => [],
        self::ENVIRONMENTAL_OPERATORS_REGISTER => [],
        self::ANAC => [],
        self::RELIANCE => [],
        self::GRADE_CONSORTIUM => [],
        self::TRAINING_COURSE => [],
        self::TENDERS => [],
        self::TENDER_JOURNAL => [],
        self::MEPA => [],
        self::TECHNICAL_PROPOSAL => [],
        self::JOINT_VENTURES => [],
        self::WHITE_LIST => [],
    ];

    /**
     * Category name => the product typology its products are filed under, by
     * CODE (ProductTypologySeeder): "tipologia consulenza" for the whole list.
     *
     * @var array<string, string>
     */
    public const array TYPOLOGIES = [
        self::SUPPLIER_REGISTERS => 'consultancy',
        self::ENVIRONMENTAL_OPERATORS_REGISTER => 'consultancy',
        self::ANAC => 'consultancy',
        self::RELIANCE => 'consultancy',
        self::GRADE_CONSORTIUM => 'consultancy',
        self::TRAINING_COURSE => 'consultancy',
        self::TENDERS => 'consultancy',
        self::TENDER_JOURNAL => 'consultancy',
        self::MEPA => 'consultancy',
        self::TECHNICAL_PROPOSAL => 'consultancy',
        self::JOINT_VENTURES => 'consultancy',
        self::WHITE_LIST => 'consultancy',
    ];

    private const array NOTICE_AND_RULES = ['code' => 'tender_notice_and_rules', 'name' => 'Bando e disciplinare', 'type' => 'boolean'];

    private const array TENDER_ATTACHMENTS = ['code' => 'tender_attachments', 'name' => 'Allegati di gara', 'type' => 'boolean'];

    private const array COMPANY_REPORT = ['code' => 'tender_company_report', 'name' => 'Visura camerale aggiornata', 'type' => 'boolean'];

    private const array LAST_FINANCIAL_STATEMENT = ['code' => 'tender_last_financial_statement', 'name' => 'Ultimo bilancio depositato', 'type' => 'boolean'];

    private const array LAST_THREE_FINANCIAL_STATEMENTS = ['code' => 'tender_last_three_financial_statements', 'name' => 'Ultimi 3 bilanci depositati', 'type' => 'boolean'];

    private const array DIRECTOR_ID_DOCUMENT = ['code' => 'tender_director_id_document', 'name' => 'Documento dell\'amministratore', 'type' => 'boolean'];

    private const array MAJORITY_SHAREHOLDERS_ID_DOCUMENTS = ['code' => 'tender_majority_shareholders_id_documents', 'name' => 'Documento dei soci di maggioranza', 'type' => 'boolean'];

    private const array SOA_CERTIFICATE = ['code' => 'tender_soa_certificate', 'name' => 'Eventuale Attestazione SOA', 'type' => 'boolean'];

    private const array WHITE_LIST_REGISTRATION = ['code' => 'tender_white_list_registration', 'name' => 'Eventuale White List', 'type' => 'boolean'];

    private const array ISO_CERTIFICATIONS = ['code' => 'tender_iso_certifications', 'name' => 'Eventuali certificazioni ISO', 'type' => 'boolean'];

    private const array FAMILY_MEMBERS_STATEMENT = ['code' => 'tender_family_members_statement', 'name' => 'Dichiarazione familiari e conviventi', 'type' => 'boolean'];

    private const array COMPANY_REPORT_OR_ID_DOCUMENT = ['code' => 'tender_company_report_or_id_document', 'name' => 'Visura/documento d\'identità', 'type' => 'boolean'];

    /**
     * The documents of a tender to prepare: "avvalimenti" and "preparazione
     * gara e progetto tecnico" ask for the same list.
     */
    private const array TENDER_DOCUMENTS = [
        self::NOTICE_AND_RULES,
        self::TENDER_ATTACHMENTS,
        self::COMPANY_REPORT,
        self::LAST_FINANCIAL_STATEMENT,
        self::DIRECTOR_ID_DOCUMENT,
        self::SOA_CERTIFICATE,
        self::WHITE_LIST_REGISTRATION,
    ];

    /**
     * The documents of a registration: "iscrizione af, mepa e anac".
     */
    private const array REGISTRATION_DOCUMENTS = [
        self::COMPANY_REPORT,
        self::DIRECTOR_ID_DOCUMENT,
        self::MAJORITY_SHAREHOLDERS_ID_DOCUMENTS,
        self::LAST_THREE_FINANCIAL_STATEMENTS,
        self::ISO_CERTIFICATIONS,
        self::SOA_CERTIFICATE,
    ];

    /**
     * The OFFERTA-context fields, category name => its documents, for
     * QualificaCatalogSeeder::CATALOG_QUOTE_ATTRIBUTES. A document asked by
     * several lines is one attribute assigned to each of them.
     *
     * @var array<string, list<array{code: string, name: string, type: string}>>
     */
    public const array QUOTE_ATTRIBUTES = [
        self::RELIANCE => self::TENDER_DOCUMENTS,
        self::TENDERS => self::TENDER_DOCUMENTS,
        self::TECHNICAL_PROPOSAL => self::TENDER_DOCUMENTS,
        self::SUPPLIER_REGISTERS => self::REGISTRATION_DOCUMENTS,
        self::MEPA => self::REGISTRATION_DOCUMENTS,
        self::ANAC => self::REGISTRATION_DOCUMENTS,
        self::WHITE_LIST => [self::COMPANY_REPORT, self::DIRECTOR_ID_DOCUMENT, self::FAMILY_MEMBERS_STATEMENT],
        self::TENDER_JOURNAL => [self::COMPANY_REPORT],
        self::TRAINING_COURSE => [self::COMPANY_REPORT_OR_ID_DOCUMENT],
    ];

    /**
     * Every line's form is one "Documenti da allegare" section; on an offer
     * mixing several lines a shared document shows once (AttributeLayoutMerger,
     * first category wins).
     *
     * @var array{variant: LayoutSectionVariant, columns: int, description: string}
     */
    private const array DOCUMENTS_SECTION_STYLE = ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Documenti da allegare alla richiesta.'];

    private const string DOCUMENTS_SECTION_ID = 'tender-documents';

    private const string DOCUMENTS_SECTION_TITLE = 'Documenti da allegare';

    private const array TENDER_FORM = [[self::DOCUMENTS_SECTION_ID, self::DOCUMENTS_SECTION_TITLE, [
        ['tender_notice_and_rules', 'tender_attachments'],
        ['tender_company_report', 'tender_last_financial_statement'],
        ['tender_director_id_document', 'tender_soa_certificate'],
        ['tender_white_list_registration'],
    ], self::DOCUMENTS_SECTION_STYLE]];

    private const array REGISTRATION_FORM = [[self::DOCUMENTS_SECTION_ID, self::DOCUMENTS_SECTION_TITLE, [
        ['tender_company_report', 'tender_director_id_document'],
        ['tender_majority_shareholders_id_documents', 'tender_last_three_financial_statements'],
        ['tender_iso_certifications', 'tender_soa_certificate'],
    ], self::DOCUMENTS_SECTION_STYLE]];

    /**
     * The offer form of each line with documents, for
     * QualificaQuoteLayoutSeeder::OWN_FORMS: one row per category, as a layout
     * is never inherited from a sibling.
     *
     * @var array<string, list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>>
     */
    public const array QUOTE_FORMS = [
        self::RELIANCE => self::TENDER_FORM,
        self::TENDERS => self::TENDER_FORM,
        self::TECHNICAL_PROPOSAL => self::TENDER_FORM,
        self::SUPPLIER_REGISTERS => self::REGISTRATION_FORM,
        self::MEPA => self::REGISTRATION_FORM,
        self::ANAC => self::REGISTRATION_FORM,
        self::WHITE_LIST => [[self::DOCUMENTS_SECTION_ID, self::DOCUMENTS_SECTION_TITLE, [
            ['tender_company_report', 'tender_director_id_document'],
            ['tender_family_members_statement'],
        ], self::DOCUMENTS_SECTION_STYLE]],
        self::TENDER_JOURNAL => [[self::DOCUMENTS_SECTION_ID, self::DOCUMENTS_SECTION_TITLE, [
            ['tender_company_report'],
        ], self::DOCUMENTS_SECTION_STYLE]],
        self::TRAINING_COURSE => [[self::DOCUMENTS_SECTION_ID, self::DOCUMENTS_SECTION_TITLE, [
            ['tender_company_report_or_id_document'],
        ], self::DOCUMENTS_SECTION_STYLE]],
    ];
}
