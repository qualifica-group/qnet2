<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The "ISO" branch of the catalogue (user directive 2026-10-21): a root of
 * its own, linked to the "ISO" business function, whose subcategories
 * (IsoSubcategories, one per line of the "iso" service list) inherit the
 * root's fields and forms. Pure data, read by QualificaCatalogSeeder (offer
 * fields), QualificaQuoteLayoutSeeder (offer form),
 * QualificaBranchWorkOrderSeeder (Commessa fields and form, in
 * IsoWorkOrderCatalogue), CatalogRootRules and QualificaBusinessFunctionLinkSeeder.
 *
 * The offer fields are the legacy "Informazioni Preliminari Iso" of the
 * opportunity. Like the legacy, every norm is its own checkbox — the entries
 * of ONE checkbox-list field (spec 0424, user directive 2026-11-23) — and a
 * norm-group block opens when ANY of its norms is ticked (SA8000 and 13549
 * open two blocks, as in the legacy `PreliminaryInfo\Iso`). Until 2026-11-23
 * the form had one yes/no field per group, moved over by
 * IsoRequestedStandardsSeeder. "Altro" opens nothing: it is a free-text field
 * under the list (user directive 2026-10-30). The attachment has no field type
 * and is not carried over.
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

    /** The checkbox list of the norms, in the legacy order. */
    public const string STANDARDS = 'iso_requested_standards';

    public const array STANDARD_OPTIONS = [
        ['value' => 'standard_9001', 'label' => '9001'],
        ['value' => 'standard_14001', 'label' => '14001'],
        ['value' => 'standard_45001', 'label' => '45001'],
        ['value' => 'standard_37001', 'label' => '37001'],
        ['value' => 'standard_39001', 'label' => '39001'],
        ['value' => 'standard_27001', 'label' => '27001'],
        ['value' => 'standard_50001', 'label' => '50001'],
        ['value' => 'standard_sa8000', 'label' => 'SA8000'],
        ['value' => 'standard_13549', 'label' => '13549'],
        ['value' => 'standard_30415', 'label' => 'Parità di genere + ISO 30415'],
        ['value' => 'standard_brc', 'label' => 'BRC'],
        ['value' => 'standard_ifs', 'label' => 'IFS'],
        ['value' => 'standard_22000', 'label' => '22000'],
        ['value' => 'standard_22005', 'label' => '22005'],
        ['value' => 'standard_emas', 'label' => 'EMAS'],
        ['value' => 'standard_ecolabel', 'label' => 'Ecolabel'],
        ['value' => 'standard_1090', 'label' => '1090'],
        ['value' => 'standard_16636', 'label' => '16636'],
    ];

    /**
     * The norms that open each group block of QUOTE_SECTIONS, keyed by the
     * block id (legacy `PreliminaryInfo\Iso::$group1..6`).
     *
     * @var array<string, list<string>>
     */
    public const array GROUP_STANDARDS = [
        'iso-group-general' => ['standard_9001', 'standard_14001', 'standard_45001', 'standard_27001', 'standard_sa8000', 'standard_13549', 'standard_emas', 'standard_ecolabel', 'standard_1090', 'standard_16636'],
        'iso-group-food' => ['standard_brc', 'standard_ifs', 'standard_22000', 'standard_22005', 'standard_sa8000', 'standard_13549'],
        'iso-group-energy' => ['standard_50001'],
        'iso-group-anti-bribery' => ['standard_37001'],
        'iso-group-road-safety' => ['standard_39001'],
        'iso-group-gender-equality' => ['standard_30415'],
    ];

    /** The norms outside the six groups, written by hand. */
    private const string OTHER_STANDARD = 'iso_other_standard';

    /**
     * The OFFERTA-context fields, assigned on the root and inherited by its
     * subcategory, in the legacy screen order.
     *
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, config?: array<string, mixed>}>
     */
    public const array QUOTE_ATTRIBUTES = [
        ['code' => self::STANDARDS, 'name' => 'Norme richieste', 'type' => 'enum', 'config' => ['display' => 'checklist'], 'options' => self::STANDARD_OPTIONS],
        ['code' => self::OTHER_STANDARD, 'name' => 'Altro', 'type' => 'text'],
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

    private const string GROUP_DESCRIPTION = 'Compare quando in Norme richieste è spuntata almeno una norma del gruppo.';

    /**
     * The offer form, in the legacy reading order: the norm list on top,
     * each group block conditional on its norms (spec 0424).
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string, visible_when?: string, visible_when_options?: list<string>}}>
     */
    public const array QUOTE_SECTIONS = [
        ['iso-standards', 'Certificazioni richieste', [
            [self::STANDARDS],
            [self::OTHER_STANDARD],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Spunta le norme richieste: per ognuna si apre il blocco del suo gruppo. Le altre norme vanno in Altro.']],
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
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::STANDARDS, 'visible_when_options' => self::GROUP_STANDARDS['iso-group-general']]],
        ['iso-group-food', 'Info per standard BRC/IFS/22000/22005', [
            ['iso_g2_surface_sqm', 'iso_g2_production_lines'],
            ['iso_g2_processes', 'iso_g2_haccp_plans'],
            ['iso_g2_certification_type', 'iso_g2_activity_description'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::STANDARDS, 'visible_when_options' => self::GROUP_STANDARDS['iso-group-food']]],
        ['iso-group-energy', 'Info per standard 50001', [
            ['iso_g3_total_employees', 'iso_g3_energy_employees'],
            ['iso_g3_certification_type', 'iso_g3_energy_sources'],
            ['iso_g3_electricity_consumption'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::STANDARDS, 'visible_when_options' => self::GROUP_STANDARDS['iso-group-energy']]],
        ['iso-group-anti-bribery', 'Info per standard 37001', [
            ['iso_g4_high_risk_employees', 'iso_g4_certification_type'],
            ['iso_g4_medium_risk_employees', 'iso_g4_low_risk_employees'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::STANDARDS, 'visible_when_options' => self::GROUP_STANDARDS['iso-group-anti-bribery']]],
        ['iso-group-road-safety', 'Info per standard 39001', [
            ['iso_g5_drivers', 'iso_g5_process_employees'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::STANDARDS, 'visible_when_options' => self::GROUP_STANDARDS['iso-group-road-safety']]],
        ['iso-group-gender-equality', 'Info per standard Parità di genere + 30415', [
            ['iso_g6_managers', 'iso_g6_clerks_workers'],
            ['iso_g6_male_female_ratio', 'iso_g6_online_discrimination'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => self::GROUP_DESCRIPTION, 'visible_when' => self::STANDARDS, 'visible_when_options' => self::GROUP_STANDARDS['iso-group-gender-equality']]],
        ['iso-consulting', 'Tipologia di consulenza richiesta', [
            ['iso_service_type', 'iso_audit_assistance'],
            ['iso_initial_consulting_days', 'iso_surveillance_consulting_days'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Servizio richiesto e giornate di consulenza.']],
    ];
}
