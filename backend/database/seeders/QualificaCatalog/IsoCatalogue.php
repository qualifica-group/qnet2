<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The "ISO" branch of the catalogue (user directive 2026-10-21): a root of
 * its own, linked to the "ISO" business function, with one classification
 * target, "ISO - Certificazione", which inherits the root's fields and forms.
 * No product is seeded: they are added from the catalogue. Pure data, read by
 * QualificaCatalogSeeder (tree and offer fields), QualificaQuoteLayoutSeeder
 * (offer form), QualificaBranchWorkOrderSeeder (Commessa fields and form, in
 * IsoWorkOrderCatalogue), CatalogRootRules and QualificaBusinessFunctionLinkSeeder.
 *
 * The offer fields are the legacy "Informazioni Preliminari Iso" of the
 * opportunity. The legacy opens each norm-group block when ANY of its norms is
 * ticked; a section opens on ONE yes/no field (spec 0288), so the 19 norm
 * checkboxes became one checkbox per group, each opening its block (user
 * decision 2026-10-21). "Altro" opened nothing and the attachment has no field
 * type: neither is carried over.
 *
 * The legacy codes (grp1_certification, check_9001...) stay on the "[OLD]"
 * categories as history: this branch uses English codes of its own, with the
 * same option lists, labels tidied (typos fixed, no shouting).
 *
 * `code` is the English identifier; `name` and the option labels are the
 * user-facing values, kept in their original language.
 */
final class IsoCatalogue
{
    public const string ROOT = 'ISO';

    public const string BUSINESS_FUNCTION = 'ISO';

    public const string CERTIFICATION_CATEGORY = 'ISO - Certificazione';

    /**
     * The root's subcategory, for QualificaCatalogSeeder::CATALOG...
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [self::CERTIFICATION_CATEGORY => []];

    /**
     * ...a classification target, never a container (spec 0074).
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [self::CERTIFICATION_CATEGORY];

    private const array CHECKBOX = ['display' => 'checkbox'];

    private const array RADIO = ['display' => 'radio'];

    /** The legacy "Tipo Certificazione Richiesta" list, shared by the four groups that ask it. */
    private const array CERTIFICATION_TYPES = [
        ['value' => 'first_certification', 'label' => 'Prima certificazione'],
        ['value' => 'scope_extension', 'label' => 'Estensione di scopo'],
        ['value' => 'site_extension', 'label' => 'Estensione di sito'],
        ['value' => 'recertification', 'label' => 'Ricertificazione'],
        ['value' => 'certificate_transfer', 'label' => 'Trasferimento di certificato'],
        ['value' => 'certificate_change', 'label' => 'Variazione di certificato'],
    ];

    private const array SITES_CONFIG = ['columns' => [
        ['key' => 'address', 'label' => 'Indirizzo', 'type' => 'text'],
        ['key' => 'postal_code', 'label' => 'CAP', 'type' => 'text'],
        ['key' => 'city', 'label' => 'Città', 'type' => 'text'],
        ['key' => 'province', 'label' => 'Provincia', 'type' => 'text'],
        ['key' => 'site_type', 'label' => 'Tipo sede', 'type' => 'enum', 'options' => [
            ['value' => 'delivery', 'label' => 'Consegna'],
            ['value' => 'billing', 'label' => 'Fatturazione'],
            ['value' => 'operational', 'label' => 'Sede operativa'],
        ]],
        ['key' => 'certification_requested', 'label' => 'Cert. richiesta', 'type' => 'boolean'],
        ['key' => 'activities', 'label' => 'Attività svolte', 'type' => 'text'],
        ['key' => 'employees', 'label' => 'N. addetti per sito', 'type' => 'integer'],
    ]];

    /**
     * The group checkboxes, each opening the block of QUOTE_SECTIONS that
     * names it in `visible_when`.
     */
    private const string GROUP_GENERAL = 'iso_group_general';

    private const string GROUP_FOOD = 'iso_group_food';

    private const string GROUP_ENERGY = 'iso_group_energy';

    private const string GROUP_ANTI_BRIBERY = 'iso_group_anti_bribery';

    private const string GROUP_ROAD_SAFETY = 'iso_group_road_safety';

    private const string GROUP_GENDER_EQUALITY = 'iso_group_gender_equality';

    /**
     * The OFFERTA-context fields, assigned on the root and inherited by its
     * subcategory, in the legacy screen order.
     *
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, config?: array<string, mixed>}>
     */
    public const array QUOTE_ATTRIBUTES = [
        ['code' => self::GROUP_GENERAL, 'name' => 'Standard 1090, 9001, 13549, 14001, 16636, 27001, 45001, Ecolabel, EMAS, SA8000', 'type' => 'boolean', 'config' => self::CHECKBOX],
        ['code' => self::GROUP_FOOD, 'name' => 'Standard BRC, IFS, 22000, 22005', 'type' => 'boolean', 'config' => self::CHECKBOX],
        ['code' => self::GROUP_ENERGY, 'name' => 'Standard 50001', 'type' => 'boolean', 'config' => self::CHECKBOX],
        ['code' => self::GROUP_ANTI_BRIBERY, 'name' => 'Standard 37001', 'type' => 'boolean', 'config' => self::CHECKBOX],
        ['code' => self::GROUP_ROAD_SAFETY, 'name' => 'Standard 39001', 'type' => 'boolean', 'config' => self::CHECKBOX],
        ['code' => self::GROUP_GENDER_EQUALITY, 'name' => 'Parità di genere + 30415', 'type' => 'boolean', 'config' => self::CHECKBOX],
        ['code' => 'iso_application_scope', 'name' => 'Campo di applicazione', 'type' => 'textarea'],
        ['code' => 'iso_active_sites', 'name' => 'N° cantieri/commesse attive', 'type' => 'text'],
        ['code' => 'iso_urgent', 'name' => 'Urgente', 'type' => 'boolean'],
        ['code' => 'iso_previous_body', 'name' => 'Ente vecchio', 'type' => 'text'],
        ['code' => 'iso_requested_body', 'name' => 'Ente richiesto per l\'emissione', 'type' => 'text'],
        ['code' => 'iso_certificate_expiry_date', 'name' => 'Data scadenza certificato', 'type' => 'date'],
        ['code' => 'iso_first_audit_date', 'name' => 'Data I° audit', 'type' => 'date'],
        ['code' => 'iso_requested_issue_date', 'name' => 'Data emissione richiesta entro', 'type' => 'date'],
        ['code' => 'iso_issue_reason_notes', 'name' => 'Note motivazione emissione', 'type' => 'textarea'],
        ['code' => 'iso_application_scope_notes', 'name' => 'Note campo di applicazione', 'type' => 'text'],
        ['code' => 'iso_legal_site_certified', 'name' => 'La sede legale è oggetto della certificazione?', 'type' => 'boolean'],
        ['code' => 'iso_sites', 'name' => 'Sedi', 'type' => 'table', 'config' => self::SITES_CONFIG],
        ['code' => 'iso_g1_total_employees', 'name' => 'Numero addetti totale', 'type' => 'integer'],
        ['code' => 'iso_g1_revenue', 'name' => 'Fatturato', 'type' => 'decimal'],
        ['code' => 'iso_g1_certification_type', 'name' => 'Tipo certificazione richiesta', 'type' => 'enum', 'options' => self::CERTIFICATION_TYPES],
        ['code' => 'iso_g1_notes', 'name' => 'Note', 'type' => 'text'],
        ['code' => 'iso_g2_surface_sqm', 'name' => 'Superficie in mq', 'type' => 'decimal'],
        ['code' => 'iso_g2_production_lines', 'name' => 'N° linee produttive', 'type' => 'integer'],
        ['code' => 'iso_g2_processes', 'name' => 'Numero processi', 'type' => 'integer'],
        ['code' => 'iso_g2_haccp_plans', 'name' => 'Numero di piani HACCP', 'type' => 'integer'],
        ['code' => 'iso_g2_certification_type', 'name' => 'Tipo certificazione richiesta (BRC, IFS, 22000, 22005)', 'type' => 'enum', 'options' => self::CERTIFICATION_TYPES],
        ['code' => 'iso_g2_activity_description', 'name' => 'Descrizione attività aziendali', 'type' => 'text'],
        ['code' => 'iso_g3_total_employees', 'name' => 'N° totale addetti', 'type' => 'integer'],
        ['code' => 'iso_g3_energy_employees', 'name' => 'N° addetti coinvolti significativamente nel consumo di energia', 'type' => 'integer'],
        ['code' => 'iso_g3_certification_type', 'name' => 'Tipo certificazione richiesta (50001)', 'type' => 'enum', 'options' => self::CERTIFICATION_TYPES],
        ['code' => 'iso_g3_energy_sources', 'name' => 'N° fonti energetiche utilizzate', 'type' => 'integer'],
        ['code' => 'iso_g3_electricity_consumption', 'name' => 'Quantità di consumo elettricità', 'type' => 'enum', 'config' => self::RADIO, 'options' => [
            ['value' => 'low', 'label' => 'Bassa < 550.000 kWh/anno'],
            ['value' => 'medium', 'label' => 'Media < 5.500.000 kWh/anno'],
            ['value' => 'high', 'label' => 'Alta > 5,5 mln kWh/anno'],
        ]],
        ['code' => 'iso_g4_high_risk_employees', 'name' => 'N° addetti alto rischio corruzione', 'type' => 'integer'],
        ['code' => 'iso_g4_certification_type', 'name' => 'Tipo certificazione richiesta (37001)', 'type' => 'enum', 'options' => self::CERTIFICATION_TYPES],
        ['code' => 'iso_g4_medium_risk_employees', 'name' => 'N° addetti medio rischio (impiegati)', 'type' => 'integer'],
        ['code' => 'iso_g4_low_risk_employees', 'name' => 'N° addetti basso rischio (operai)', 'type' => 'integer'],
        ['code' => 'iso_g5_drivers', 'name' => 'N° autisti', 'type' => 'integer'],
        ['code' => 'iso_g5_process_employees', 'name' => 'Addetti coinvolti nel processo + amministratori + responsabile del sistema', 'type' => 'integer'],
        ['code' => 'iso_g6_managers', 'name' => 'N° addetti dirigenti + quadri', 'type' => 'integer'],
        ['code' => 'iso_g6_clerks_workers', 'name' => 'N° addetti impiegati + operai', 'type' => 'integer'],
        ['code' => 'iso_g6_male_female_ratio', 'name' => 'Rapporto addetti uomo/donna', 'type' => 'integer'],
        ['code' => 'iso_g6_online_discrimination', 'name' => 'Presenti discriminazioni online', 'type' => 'boolean'],
        ['code' => 'iso_service_type', 'name' => 'Tipologia di consulenza richiesta', 'type' => 'enum', 'config' => self::RADIO, 'options' => [
            ['value' => 'certificate_only', 'label' => 'Solo certificato'],
            ['value' => 'management_consulting', 'label' => 'Consulenza di gestione'],
            ['value' => 'certificate_and_consulting', 'label' => 'Certificato + consulenza'],
        ]],
        ['code' => 'iso_audit_assistance', 'name' => 'Affiancamento in verifica', 'type' => 'boolean'],
        ['code' => 'iso_initial_consulting_days', 'name' => 'N° G/U consulenza iniziale', 'type' => 'integer'],
        ['code' => 'iso_surveillance_consulting_days', 'name' => 'N° G/U consulenza sorveglianza', 'type' => 'integer'],
    ];

    private const string GROUP_DESCRIPTION = 'Compare quando il gruppo di norme è spuntato in Certificazioni richieste.';

    /**
     * The offer form, in the legacy reading order: the group checkboxes on
     * top, each group block conditional on its checkbox (spec 0288).
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string, visible_when?: string}}>
     */
    public const array QUOTE_SECTIONS = [
        ['iso-standards', 'Certificazioni richieste', [
            [self::GROUP_GENERAL],
            [self::GROUP_FOOD, self::GROUP_ENERGY],
            [self::GROUP_ANTI_BRIBERY, self::GROUP_ROAD_SAFETY],
            [self::GROUP_GENDER_EQUALITY],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Spunta i gruppi di norme richiesti: per ognuno si apre il blocco con le sue informazioni.']],
        ['iso-certification', 'Informazioni relative alla certificazione', [
            ['iso_application_scope'],
            ['iso_active_sites', 'iso_urgent'],
            ['iso_previous_body', 'iso_requested_body'],
            ['iso_certificate_expiry_date', 'iso_first_audit_date'],
            ['iso_requested_issue_date'],
            ['iso_issue_reason_notes'],
            ['iso_application_scope_notes'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Campo di applicazione, enti e date della certificazione.']],
        ['iso-organisation', 'Dati organizzazione che richiede la certificazione', [
            ['iso_legal_site_certified'],
            ['iso_sites'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Sede legale e sedi oggetto della certificazione.']],
        ['iso-group-general', 'Info per standard 1090/9001/13549/14001/16636/27001/45001/Ecolabel/EMAS/SA8000', [
            ['iso_g1_total_employees', 'iso_g1_revenue'],
            ['iso_g1_certification_type', 'iso_g1_notes'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::GROUP_GENERAL]],
        ['iso-group-food', 'Info per standard BRC/IFS/22000/22005', [
            ['iso_g2_surface_sqm', 'iso_g2_production_lines'],
            ['iso_g2_processes', 'iso_g2_haccp_plans'],
            ['iso_g2_certification_type', 'iso_g2_activity_description'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::GROUP_FOOD]],
        ['iso-group-energy', 'Info per standard 50001', [
            ['iso_g3_total_employees', 'iso_g3_energy_employees'],
            ['iso_g3_certification_type', 'iso_g3_energy_sources'],
            ['iso_g3_electricity_consumption'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::GROUP_ENERGY]],
        ['iso-group-anti-bribery', 'Info per standard 37001', [
            ['iso_g4_high_risk_employees', 'iso_g4_certification_type'],
            ['iso_g4_medium_risk_employees', 'iso_g4_low_risk_employees'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::GROUP_ANTI_BRIBERY]],
        ['iso-group-road-safety', 'Info per standard 39001', [
            ['iso_g5_drivers', 'iso_g5_process_employees'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::GROUP_ROAD_SAFETY]],
        ['iso-group-gender-equality', 'Info per standard Parità di genere + 30415', [
            ['iso_g6_managers', 'iso_g6_clerks_workers'],
            ['iso_g6_male_female_ratio', 'iso_g6_online_discrimination'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::GROUP_GENDER_EQUALITY]],
        ['iso-consulting', 'Tipologia di consulenza richiesta', [
            ['iso_service_type', 'iso_audit_assistance'],
            ['iso_initial_consulting_days', 'iso_surveillance_consulting_days'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Servizio richiesto e giornate di consulenza.']],
    ];
}
