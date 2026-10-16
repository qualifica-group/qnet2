<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The "SOA" branch of the catalogue (user directive 2026-10-15, "Q-crm SOA"
 * sheet and "PRODOTTI SOA" list): a root of its own, linked to the "SOA"
 * business function, with two classification targets — "SOA - Ente" (the
 * attestations bought from a certifying body, one product per body and
 * service) and "SOA - Consulenza" (the yearly consultancy). Pure data, read by
 * QualificaCatalogSeeder (tree and offer fields), QualificaQuoteLayoutSeeder
 * (offer form), QualificaSoaWorkOrderSeeder (Commessa fields and form),
 * CatalogProducts and BranchProducts (products, suppliers, typologies and "Mesi scadenza"),
 * CatalogRootRules and QualificaBusinessFunctionLinkSeeder.
 *
 * The subcategories carry the branch as prefix, never a bare "Ente" /
 * "Consulenza": the name is the catalogue's natural key and "Consulenza" is
 * already a root (user decisions 2026-10-15; renamed from "Ente SOA" /
 * "Consulenza SOA", see CatalogCategoryRenames). Both inherit the root's
 * fields and forms.
 *
 * NOT FIELDS, by design (user decision 2026-10-15): the Commessa's "Stato
 * Lavorazione" — a SOA deal uses the default working states — and the
 * "Descrizione Commessa" block, which is the work order's own number and type.
 * The legacy codes (servizio_soa, soa_scadenza...) stay on the "[OLD]"
 * categories as history: this branch uses English codes of its own, with the
 * same option lists, labels tidied (no shouting, no stray spaces).
 *
 * `code` is the English identifier; `name` and the option labels are the
 * user-facing values, kept in their original language.
 */
final class SoaCatalogue
{
    public const string ROOT = 'SOA';

    public const string BUSINESS_FUNCTION = 'SOA';

    public const string CERTIFYING_BODY_CATEGORY = 'SOA - Ente';

    public const string CONSULTING_CATEGORY = 'SOA - Consulenza';

    /**
     * The root's subcategories, for QualificaCatalogSeeder::CATALOG: both host
     * their products directly, so neither has children...
     *
     * @var array<string, list<string>>
     */
    public const array SUBCATEGORIES = [
        self::CERTIFYING_BODY_CATEGORY => [],
        self::CONSULTING_CATEGORY => [],
    ];

    /**
     * ...and both are classification targets, never containers (spec 0074),
     * for QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES.
     *
     * @var list<string>
     */
    public const array TARGET_CATEGORIES = [self::CERTIFYING_BODY_CATEGORY, self::CONSULTING_CATEGORY];

    /**
     * The three certifying bodies, as their registries are named in the
     * legacy import (spelling included): a product is linked to its body only
     * when a supplier registry of that name exists (CatalogProducts).
     */
    public const string ARGENTA = 'ARGENTA SOA s.p.a.';

    public const string SOATECH = 'lA SOATECH SPA';

    public const string DAP = 'DAP SOA SPA';

    /**
     * Category name => the product typology its products are filed under, by
     * CODE (ProductTypologySeeder): the attestations are bought from a body,
     * the consultancy is Qualifica's own (user directive 2026-10-15).
     *
     * @var array<string, string>
     */
    public const array TYPOLOGIES = [
        self::CERTIFYING_BODY_CATEGORY => 'institution',
        self::CONSULTING_CATEGORY => 'consultancy',
    ];

    /**
     * Category name => product name => its supplier's registry name (null: no
     * supplier) and its "Mesi scadenza". Sold at 0 with the 22% VAT rate, like
     * the legacy rows.
     *
     * @var array<string, array<string, array{supplier: string|null, expiration_months: int}>>
     */
    public const array PRODUCTS = [
        self::CERTIFYING_BODY_CATEGORY => [
            'Attestazione Soa_Soatech' => ['supplier' => self::SOATECH, 'expiration_months' => 0],
            'Soa_Revisione_Soatech' => ['supplier' => self::SOATECH, 'expiration_months' => 0],
            'Soa_Rinnovo_Soatech' => ['supplier' => self::SOATECH, 'expiration_months' => 0],
            'Soa_Integrazione_Soatech' => ['supplier' => self::SOATECH, 'expiration_months' => 0],
            'Soa_Variazione_Soatech' => ['supplier' => self::SOATECH, 'expiration_months' => 0],
            'Attestazione Soa_Argenta' => ['supplier' => self::ARGENTA, 'expiration_months' => 0],
            'Soa_Integrazione_Argenta' => ['supplier' => self::ARGENTA, 'expiration_months' => 0],
            'Soa_Revisione_Argenta' => ['supplier' => self::ARGENTA, 'expiration_months' => 0],
            'Soa_Rinnovo_Argenta' => ['supplier' => self::ARGENTA, 'expiration_months' => 0],
            'Soa_Variazione_Argenta' => ['supplier' => self::ARGENTA, 'expiration_months' => 0],
            'Attestazione Soa_Dap' => ['supplier' => self::DAP, 'expiration_months' => 0],
            'Soa_Integrazione_Dap' => ['supplier' => self::DAP, 'expiration_months' => 0],
            'Soa_Revisione_Dap' => ['supplier' => self::DAP, 'expiration_months' => 0],
            'Soa_Rinnovo_Dap' => ['supplier' => self::DAP, 'expiration_months' => 0],
            'Soa_Variazione_Dap' => ['supplier' => self::DAP, 'expiration_months' => 0],
        ],
        self::CONSULTING_CATEGORY => [
            'Consulenza Soa_I anno' => ['supplier' => null, 'expiration_months' => 0],
            'Consulenza Soa_II anno' => ['supplier' => null, 'expiration_months' => 12],
            'Consulenza Soa_III anno' => ['supplier' => null, 'expiration_months' => 24],
        ],
    ];

    /**
     * The OFFERTA-context fields ("Informazioni Preliminari Soa"), assigned on
     * the root and inherited by both subcategories.
     *
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>}>
     */
    public const array QUOTE_ATTRIBUTES = [
        ['code' => 'soa_service', 'name' => 'Servizio SOA', 'type' => 'enum', 'options' => [
            ['value' => 'first_issue', 'label' => 'Prima emissione'],
            ['value' => 'three_year_renewal', 'label' => 'Rinnovo triennale'],
            ['value' => 'five_year_renewal', 'label' => 'Rinnovo quinquennale'],
            ['value' => 'category_extension', 'label' => 'Integrazione di categoria'],
            ['value' => 'corporate_change', 'label' => 'Variazione societaria'],
        ]],
        ['code' => 'soa_category', 'name' => 'Categorie SOA', 'type' => 'enum', 'options' => SoaCategoryOptions::OPTIONS],
        ['code' => 'soa_classification', 'name' => 'Elenco classifica', 'type' => 'enum', 'options' => [
            ['value' => 'i', 'label' => 'I'],
            ['value' => 'ii', 'label' => 'II'],
            ['value' => 'iii', 'label' => 'III'],
            ['value' => 'iii_bis', 'label' => 'III bis'],
            ['value' => 'iv', 'label' => 'IV'],
            ['value' => 'iv_bis', 'label' => 'IV bis'],
            ['value' => 'v', 'label' => 'V'],
            ['value' => 'vi', 'label' => 'VI'],
            ['value' => 'vii', 'label' => 'VII'],
            ['value' => 'viii', 'label' => 'VIII'],
        ]],
        ['code' => 'soa_notes', 'name' => 'Note', 'type' => 'textarea'],
        ['code' => 'soa_processing_notes', 'name' => 'Note lavorazione', 'type' => 'textarea'],
        ['code' => 'soa_payment_arrangements', 'name' => 'Eventuali accordi di pagamento', 'type' => 'textarea'],
    ];

    /**
     * The offer form, in the sheet's reading order.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array QUOTE_SECTIONS = [
        ['soa-preliminary', 'Informazioni preliminari SOA', [
            ['soa_service', 'soa_category', 'soa_classification'],
            ['soa_notes'],
            ['soa_processing_notes'],
            ['soa_payment_arrangements'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 3, 'description' => 'Servizio richiesto, categoria e classifica SOA, con le note per la lavorazione.']],
    ];

    /**
     * The COMMESSA-context fields, assigned on the root and inherited by both
     * subcategories. The legacy "Data Fine Lavorazione Presunta" and "Data
     * Chiusura Lavorazione" are two distinct dates.
     *
     * @var list<array{code: string, name: string, type: string}>
     */
    public const array WORK_ORDER_ATTRIBUTES = [
        ['code' => 'soa_work_notes', 'name' => 'Note', 'type' => 'textarea'],
        ['code' => 'soa_last_call_date', 'name' => 'Ultima chiamata', 'type' => 'date'],
        ['code' => 'soa_expected_end_date', 'name' => 'Data fine lavorazione presunta', 'type' => 'date'],
        ['code' => 'soa_closing_date', 'name' => 'Data chiusura lavorazione', 'type' => 'date'],
        ['code' => 'soa_contract_sent', 'name' => 'Invio contratto', 'type' => 'boolean'],
        ['code' => 'soa_weekly_update', 'name' => 'Aggiornamento settimanale', 'type' => 'boolean'],
        ['code' => 'soa_contract_signed_date', 'name' => 'Data firma contratto', 'type' => 'date'],
        ['code' => 'soa_issue_date', 'name' => 'Emissioni', 'type' => 'date'],
        ['code' => 'soa_three_year_check_date', 'name' => 'Verifica triennale', 'type' => 'date'],
        ['code' => 'soa_expiry_date', 'name' => 'Scadenza', 'type' => 'date'],
        ['code' => 'soa_consortium_member', 'name' => 'Appartenenza consorzio', 'type' => 'boolean'],
        ['code' => 'soa_consortium_name', 'name' => 'Nome del consorzio', 'type' => 'text'],
        ['code' => 'soa_certifying_body', 'name' => 'SOA attestante', 'type' => 'text'],
        ['code' => 'soa_iso9001_body_name', 'name' => 'Nome dell\'ente di certificazione 9001', 'type' => 'text'],
        ['code' => 'soa_iso9001_expiry_date', 'name' => 'Scadenza ente di certificazione 9001', 'type' => 'date'],
    ];

    /**
     * The Commessa form: the sheet's "Info Lavorazione SOA" and "Pianificazione
     * (da commesse precedenti)" blocks.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array WORK_ORDER_SECTIONS = [
        ['soa-processing', 'Info lavorazione SOA', [
            ['soa_work_notes'],
            ['soa_last_call_date', 'soa_expected_end_date'],
            ['soa_closing_date', 'soa_contract_signed_date'],
            ['soa_contract_sent', 'soa_weekly_update'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Indicare commenti per la lavorazione della pratica quali: urgenza e modalità operative di lavorazione.']],
        ['soa-planning', 'Pianificazione (da commesse precedenti)', [
            ['soa_issue_date', 'soa_three_year_check_date'],
            ['soa_expiry_date'],
            ['soa_consortium_member', 'soa_consortium_name'],
            ['soa_certifying_body'],
            ['soa_iso9001_body_name', 'soa_iso9001_expiry_date'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Date e dati ripresi dalle commesse precedenti del cliente.']],
    ];
}
