<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's "Dati Lavorazione Contatto" attributes (OPPORTUNITY context,
 * spec 0061): what the operator records while working a request, scoped to the
 * categories that actually use it — the Formazione branch, the self-funded
 * offer, the "DIL" subcategory and the two Consulenza leaves. Pure data, like
 * ClassroomAttributeCatalogue: QualificaContactProcessingSeeder assigns them
 * and groups them into the section named below.
 *
 * REUSED CODES (user decision 2026-07-28): where the q-crm import already
 * carries the field, the spec repeats that row's EXISTING code, label and type
 * instead of minting a parallel one — `cpi`, `profilo_cpi`, `data_scelta_cpi`,
 * `data_app_apl`, `stato_assoc_cpi`, `id_corso`, `degree`. The catalogue keeps
 * a natural key on `code`, so on an imported database those rows are ADOPTED
 * (one field, legacy history included) and on a clean one they are created
 * with the same identity. One label therefore reads as the legacy system named
 * it, not as the client's list did: `data_app_apl` is "OK app. APL" (list:
 * "Data App APL").
 *
 * `degree` is the one type conflict: the import created it as `text`, the list
 * wants a pick list. The seeder promotes it to `enum` ONLY while no request
 * carries a value for it — see QualificaContactProcessingSeeder::promoteDegree.
 */
final class ContactProcessingAttributeCatalogue
{
    /**
     * The title of the layout section grouping every attribute below in the
     * request work panel (spec 0062). User-facing, kept in its original
     * language.
     */
    public const string SECTION_TITLE = 'Dati Lavorazione Contatto';

    /**
     * The branch root carrying the training set — nodes of
     * QualificaCatalogSeeder::CATALOG, bound by identity so a rename there
     * breaks loudly here.
     */
    public const string TRAINING_CATEGORY = 'Formazione';

    public const string SELF_FUNDED_CATEGORY = 'Autofinanziato';

    /**
     * The container grouping the ten regional `GOL - <Regione>` nodes. The CPI
     * appointment TIME is assigned here, once: the children inherit it, which
     * is what "tutti i corsi GOL" means without repeating the row ten times
     * (user directive 2026-08-03).
     */
    public const string GOL_CATEGORY = 'GOL';

    /**
     * The two Consulenza leaves sharing the company-appointment set.
     *
     * @var list<string>
     */
    public const array CONSULTING_CATEGORIES = ['Trattative in Corso', 'Presa Appuntamenti'];

    /**
     * The "Formazione" subcategory carrying a set of its OWN instead of the
     * branch one: it is cut off the root by
     * QualificaCatalog\CategoryInheritanceRules, so the six specs assigned
     * here are the whole of its offer form (user directive 2026-09-10).
     */
    public const string DIL_CATEGORY = 'DIL';

    /**
     * Codes the client retired from the set — "Corso di interesse", i.e. the
     * `corso` row adopted from q-crm (user directive 2026-08-03), and "Sede",
     * i.e. `training_site` (user directive 2026-09-10). The ASSIGNMENT is
     * removed from every category on re-seed, so an installation provisioned by
     * an earlier revision converges instead of keeping a field this catalogue no
     * longer declares.
     *
     * The attribute ROW itself is left alone on purpose: `corso` is one of the
     * q-crm rows adopted rather than created here, so deleting it would cascade
     * the legacy history and fight the import that owns it, and `training_site`
     * may already carry values on offers and work orders. Unassigning is the
     * exact inverse of what this catalogue did, and it is what makes the field
     * disappear from every work panel — ApplicableAttributesResolver reads a
     * request's categories, never the global attribute list.
     *
     * @var list<string>
     */
    public const array RETIRED_ATTRIBUTES = ['corso', 'training_site'];

    /**
     * The three specs shared by the training set and the "DIL" one below —
     * extracted rather than repeated, since a second copy of the `subsidy_type`
     * option list would be one more place to keep in step. The `code` is the
     * natural key, so both categories resolve one and the same attribute row.
     *
     * @var array{code: string, name: string, type: string}
     */
    private const array CPI_APPOINTMENT_DATE = ['code' => 'data_scelta_cpi', 'name' => 'Data app. CPI', 'type' => 'date'];

    /**
     * @var array{code: string, name: string, type: string}
     */
    private const array APL_APPOINTMENT_DATE = ['code' => 'data_app_apl', 'name' => 'OK app. APL', 'type' => 'date'];

    /**
     * The client's "SFL/ADI/NASPI" pick list.
     *
     * @var array{code: string, name: string, type: string, options: list<array{value: string, label: string}>}
     */
    private const array SUBSIDY_TYPE = ['code' => 'subsidy_type', 'name' => 'Tipologia Sussidio', 'type' => 'enum', 'options' => [
        ['value' => 'naspi', 'label' => 'Naspi'],
        ['value' => 'adi', 'label' => 'Adi'],
        ['value' => 'sfl', 'label' => 'SFL'],
    ]];

    /**
     * Category name => its own attribute specs, in the client's order.
     * `code` is the English identifier (natural key, `^[a-z0-9_]+$`) except
     * for the adopted legacy rows documented above; `name` is the user-facing
     * label, kept in its original language.
     *
     * @var array<string, list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>}>>
     */
    public const array ATTRIBUTES = [
        self::TRAINING_CATEGORY => [
            self::CPI_APPOINTMENT_DATE,
            self::APL_APPOINTMENT_DATE,
            ['code' => 'dote_remaining_hours', 'name' => 'Residuo Ore Dote', 'type' => 'integer'],
            ['code' => 'stato_assoc_cpi', 'name' => 'Data associazione', 'type' => 'date'],
            ['code' => 'cpi', 'name' => 'CPI', 'type' => 'text'],
            ['code' => 'profilo_cpi', 'name' => 'Profilo CPI', 'type' => 'enum', 'options' => [
                ['value' => '101', 'label' => '101'],
                ['value' => '102', 'label' => '102'],
                ['value' => '103', 'label' => '103'],
                ['value' => '104', 'label' => '104'],
            ]],
            self::SUBSIDY_TYPE,
            ['code' => 'id_corso', 'name' => 'ID Corso', 'type' => 'text'],
            ['code' => 'gol_notice', 'name' => 'Avviso GOL', 'type' => 'text'],
            ['code' => 'application_window', 'name' => 'Finestra', 'type' => 'text'],
            ['code' => 'psp', 'name' => 'PSP', 'type' => 'boolean'],
            ['code' => 'did', 'name' => 'DID', 'type' => 'boolean'],
            ['code' => 'identity_documents', 'name' => 'Documenti Identificativi', 'type' => 'boolean'],
            ['code' => 'digital_identity', 'name' => 'SPID / CIE', 'type' => 'enum', 'options' => [
                ['value' => 'spid', 'label' => 'SPID'],
                ['value' => 'cie', 'label' => 'CIE'],
            ]],
            ['code' => 'foreign_user_documents', 'name' => 'Utenti Stranieri', 'type' => 'enum', 'options' => [
                ['value' => 'translation', 'label' => 'Traduzione'],
                ['value' => 'translation_declaration', 'label' => 'Traduzione + Dichiarazione'],
            ]],
            ['code' => self::DEGREE_ATTRIBUTE, 'name' => 'Titolo di Studio', 'type' => 'enum', 'options' => [
                ['value' => 'compulsory_education', 'label' => 'Assolvimento obbligo scolastico'],
                ['value' => 'primary_school', 'label' => 'Licenza Elementare'],
                ['value' => 'middle_school', 'label' => 'Licenza Media'],
                ['value' => 'high_school', 'label' => 'Diploma'],
                ['value' => 'degree', 'label' => 'Laurea'],
            ]],
        ],
        self::SELF_FUNDED_CATEGORY => [
            ['code' => 'course_time_preference', 'name' => 'Preferenza Orario Corso', 'type' => 'enum', 'options' => [
                ['value' => 'morning', 'label' => 'Mattina'],
                ['value' => 'afternoon', 'label' => 'Pomeriggio'],
            ]],
            ['code' => 'price', 'name' => 'Prezzo €', 'type' => 'decimal'],
        ],
        self::GOL_CATEGORY => [
            ['code' => 'ora_app_cpi', 'name' => 'Ora App. CPI', 'type' => 'text'],
        ],
        self::DIL_CATEGORY => self::DIL_ATTRIBUTES,
        'GOL - Lombardia' => self::APL_APPOINTMENT_TIME,
        'GOL - Lazio' => self::APL_APPOINTMENT_TIME,
        'GOL - Sicilia' => self::APL_APPOINTMENT_TIME,
        'Trattative in Corso' => self::CONSULTING_ATTRIBUTES,
        'Presa Appuntamenti' => self::CONSULTING_ATTRIBUTES,
    ];

    /**
     * The "DIL" offer's whole field set, in the client's order (user directive
     * 2026-09-10). Three of the six are the codes the training set already
     * declares: reused by `code`, so DIL resolves the SAME attribute row the
     * rest of the branch does — it just resolves it from its own assignment
     * rather than by inheritance, which the barrier cuts. The other three are
     * new to this catalogue.
     *
     * "Corso Scelto" is free text (user decision 2026-09-10): DIL sells one
     * service, and the course is written down, not picked off the catalogue.
     *
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>}>
     */
    private const array DIL_ATTRIBUTES = [
        ['code' => 'chosen_course', 'name' => 'Corso Scelto', 'type' => 'text'],
        self::CPI_APPOINTMENT_DATE,
        self::APL_APPOINTMENT_DATE,
        ['code' => 'dote_activation_date', 'name' => 'Data Attivazione Dote', 'type' => 'date'],
        ['code' => 'dote_expiry_date', 'name' => 'Data Scadenza Dote', 'type' => 'date'],
        self::SUBSIDY_TYPE,
    ];

    /**
     * The APL appointment TIME, assigned to the three regions whose flow books
     * one (user directive 2026-08-03). Repeated per region rather than hung on
     * GOL_CATEGORY, which would hand it to the other seven: they are siblings,
     * with no common node below GOL to carry it.
     *
     * @var list<array{code: string, name: string, type: string}>
     */
    private const array APL_APPOINTMENT_TIME = [
        ['code' => 'ora_app_apl', 'name' => 'Ora App. APL', 'type' => 'text'],
    ];

    /**
     * The company-appointment set, assigned to BOTH Consulenza leaves: they
     * are siblings, so there is no common node below the root to hang it on
     * and the root must not hand it to the rest of Consulenza.
     *
     * @var list<array{code: string, name: string, type: string}>
     */
    private const array CONSULTING_ATTRIBUTES = [
        ['code' => 'appointment_date', 'name' => 'Data Appuntamento', 'type' => 'date'],
        ['code' => 'acceptance_date', 'name' => 'Data Accettazione', 'type' => 'date'],
        ['code' => 'company_name', 'name' => 'Nome Azienda', 'type' => 'text'],
        ['code' => 'site_address', 'name' => 'Indirizzo Sede', 'type' => 'text'],
        ['code' => 'city', 'name' => 'Città', 'type' => 'text'],
        ['code' => 'requested_service', 'name' => 'Servizio Richiesto', 'type' => 'text'],
        ['code' => 'company_referent', 'name' => 'Referente Azienda', 'type' => 'text'],
    ];

    /**
     * The legacy `text` row the client's list wants as a pick list — named
     * here because the seeder's promotion step keys on it.
     */
    public const string DEGREE_ATTRIBUTE = 'degree';

    /**
     * The section's rows, paired by meaning: the CPI/APL appointment dates and
     * — column-aligned right under them — their times, the subsidy, the course
     * references, then the paperwork flags. A row's codes are
     * filtered against the target category's own effective set before being
     * written, so a category that resolves only part of the catalogue still
     * gets a coherent section: outside the GOL branch the times row drops
     * entirely, and in a region without the APL appointment it keeps the CPI
     * time alone, still under its own date. "DIL" is the extreme case — it
     * resolves six codes and nothing else, so what survives the filter is
     * exactly the client's own order: the chosen course, the two appointment
     * dates, the two DOTE ones, the subsidy.
     *
     * @var list<list<string>>
     */
    public const array ROWS = [
        ['chosen_course'],
        ['data_scelta_cpi', 'data_app_apl'],
        ['ora_app_cpi', 'ora_app_apl'],
        ['dote_activation_date', 'dote_expiry_date'],
        ['stato_assoc_cpi', 'dote_remaining_hours'],
        ['cpi', 'profilo_cpi'],
        ['subsidy_type'],
        ['id_corso'],
        ['gol_notice', 'application_window'],
        ['course_time_preference', 'price'],
        ['appointment_date', 'acceptance_date'],
        ['company_name', 'company_referent'],
        ['site_address', 'city'],
        ['requested_service'],
        ['psp', 'did'],
        ['identity_documents', 'digital_identity'],
        ['foreign_user_documents', self::DEGREE_ATTRIBUTE],
    ];
}
