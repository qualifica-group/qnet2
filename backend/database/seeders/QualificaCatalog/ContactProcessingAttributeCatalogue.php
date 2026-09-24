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
 * wants a multiselect pick list — see QualificaContactProcessingSeeder::promoteDegree.
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
     * The two Consulenza leaves. They carry NO attribute at all any more (user
     * directive 2026-09-10) — kept named because the tests assert precisely
     * that emptiness, and because RETIRED_ATTRIBUTES has to keep withdrawing
     * from them on every re-seed.
     *
     * @var list<string>
     */
    public const array CONSULTING_CATEGORIES = ['Trattative in Corso', 'Presa Appuntamenti'];

    /**
     * The "Formazione" subcategory carrying a set of its OWN instead of the
     * branch one: it is cut off the root by
     * QualificaCatalog\CategoryInheritanceRules, so the specs assigned here
     * are the whole of its offer form (user directive 2026-09-10).
     */
    public const string DIL_CATEGORY = 'DIL';

    /**
     * Codes the client retired from the set — "Corso di interesse", i.e. the
     * `corso` row adopted from q-crm (user directive 2026-08-03); "Sede", i.e.
     * `training_site`; and the whole company-appointment set the two Consulenza
     * leaves used to carry, which the client wants EMPTY — no field of their
     * own and nothing inherited (user directive 2026-09-10). The ASSIGNMENT is
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
     * `chosen_course` ("Corso Scelto") left the "DIL" set on user directive
     * 2026-09-16, replaced there by "ID Corso" and "Sede corso".
     *
     * `professional_qualification` ("Qualifica Professionale", on
     * "Autofinanziato") was folded into "Titolo di Studio" as one of its
     * options (user directive 2026-09-24).
     *
     * @var list<string>
     */
    public const array RETIRED_ATTRIBUTES = [
        'corso', 'training_site', 'chosen_course',
        // The company-appointment set: retired, not merely undeclared, or an
        // installation already seeded would keep rendering all seven.
        'appointment_date', 'acceptance_date', 'company_name',
        'site_address', 'city', 'requested_service', 'company_referent',
        'professional_qualification',
    ];

    /**
     * "Sede corso" (user directive 2026-09-10): a NEW code, deliberately NOT
     * the retired `training_site` above, which was the same idea as free
     * TEXT. Reviving that code would reinterpret every string already stored
     * on an offer or a work order ("Milano") as an operational-site ID —
     * broken references rather than migrated data. A new code leaves those
     * values where they are, unassigned and unread, and starts the relation
     * clean. Named here because the layout ROWS key on it.
     */
    public const string COURSE_SITE = 'course_site';

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
     * @var array{code: string, name: string, type: string}
     */
    private const array COURSE_ID_SPEC = ['code' => 'id_corso', 'name' => 'ID Corso', 'type' => 'text'];

    /**
     * @var array{code: string, name: string, type: string, relation_target: array<string, mixed>}
     */
    private const array COURSE_SITE_SPEC = ['code' => self::COURSE_SITE, 'name' => 'Sede corso', 'type' => 'relation', 'relation_target' => self::COURSE_SITE_RELATION_TARGET];

    /**
     * The client's "SFL/ADI/NASPI" pick list.
     *
     * @var array{code: string, name: string, type: string, options: list<array{value: string, label: string}>}
     */
    /**
     * "Sede corso" points at a single operational site (user directive
     * 2026-09-10). `operational-sites` is a valid relation target because it
     * is registered in BOTH config/tables.php and config/authorization.php —
     * the condition App\CustomFields\CustomFieldEntityRegistry imposes on
     * every `entity_type` — and it exposes the for-select endpoint the
     * frontend's RelationFieldControl queries.
     *
     * @var array<string, mixed>
     */
    private const array COURSE_SITE_RELATION_TARGET = [
        'entity_type' => 'operational-sites',
        'cardinality' => 'one',
        'for_select_resource' => 'operational-sites',
    ];

    private const array SUBSIDY_TYPE = ['code' => 'subsidy_type', 'name' => 'Tipologia Sussidio', 'type' => 'enum', 'options' => [
        ['value' => 'naspi', 'label' => 'Naspi'],
        ['value' => 'adi', 'label' => 'Adi'],
        ['value' => 'sfl', 'label' => 'SFL'],
    ]];

    /**
     * The "Titolo di Studio" pick list, a multiselect since the user directive
     * 2026-09-24 absorbed "Qualifica Professionale" as its last option.
     *
     * @var list<array{value: string, label: string}>
     */
    private const array DEGREE_OPTIONS = [
        ['value' => 'compulsory_education', 'label' => 'Assolvimento obbligo scolastico'],
        ['value' => 'primary_school', 'label' => 'Licenza Elementare'],
        ['value' => 'middle_school', 'label' => 'Licenza Media'],
        ['value' => 'high_school', 'label' => 'Diploma'],
        ['value' => 'degree', 'label' => 'Laurea'],
        ['value' => 'professional_qualification', 'label' => 'Qualifica Professionale'],
    ];

    /**
     * How "Titolo di Studio" renders — also what QualificaContactProcessingSeeder
     * applies to the row an earlier revision created as a single pick.
     *
     * @var array{display: string}
     */
    public const array DEGREE_CONFIG = ['display' => 'multiselect'];

    /**
     * Category name => its own attribute specs, in the client's order.
     * `code` is the English identifier (natural key, `^[a-z0-9_]+$`) except
     * for the adopted legacy rows documented above; `name` is the user-facing
     * label, kept in its original language.
     *
     * @var array<string, list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, config?: array<string, mixed>}>>
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
            self::COURSE_ID_SPEC,
            self::COURSE_SITE_SPEC,
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
            ['code' => self::DEGREE_ATTRIBUTE, 'name' => 'Titolo di Studio', 'type' => 'enum', 'options' => self::DEGREE_OPTIONS, 'config' => self::DEGREE_CONFIG],
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
    ];

    /**
     * "Residuo Ore", the one number of the "DIL" set — named because the
     * layout ROWS key on it.
     */
    public const string DIL_REMAINING_HOURS = 'remaining_hours';

    /**
     * The "DIL" offer's whole field set, in the client's order (user directive
     * 2026-09-10). Five of the eight are codes the training set already
     * declares: reused by `code`, so DIL resolves the SAME attribute row the
     * rest of the branch does — it just resolves it from its own assignment
     * rather than by inheritance, which the barrier cuts. The two DOTE dates
     * and "Residuo Ore" are DIL's own.
     *
     * "ID Corso" and "Sede corso" are the GOL fields, replacing the free-text
     * "Corso Scelto" (user directive 2026-09-16, see RETIRED_ATTRIBUTES).
     *
     * "Residuo Ore" (user directive 2026-09-24) is a new code rather than the
     * training set's `dote_remaining_hours`: one attribute row carries one
     * label, and the client names this one without "Dote".
     *
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, relation_target?: array<string, mixed>}>
     */
    private const array DIL_ATTRIBUTES = [
        self::CPI_APPOINTMENT_DATE,
        self::APL_APPOINTMENT_DATE,
        ['code' => 'dote_activation_date', 'name' => 'Data Attivazione Dote', 'type' => 'date'],
        ['code' => 'dote_expiry_date', 'name' => 'Data Scadenza Dote', 'type' => 'date'],
        ['code' => self::DIL_REMAINING_HOURS, 'name' => 'Residuo Ore', 'type' => 'integer'],
        self::SUBSIDY_TYPE,
        self::COURSE_ID_SPEC,
        self::COURSE_SITE_SPEC,
    ];

    /**
     * A category's OWN codes as each previous revision assigned them — frozen
     * history, never edited to follow ATTRIBUTES. The layout seeders rebuild
     * from it the effective sets those revisions composed against, or they
     * could not recognise the layouts they wrote once the set has changed.
     *
     * "DIL" before the 2026-09-16 directive, then before "Residuo Ore" joined
     * it (2026-09-24). Retired codes are listed as they stood:
     * RETIRED_ATTRIBUTES filters them out, like the retirement strips them
     * from the persisted blob.
     *
     * @var array<string, list<list<string>>>
     */
    public const array PREVIOUS_OWN_ATTRIBUTES = [
        self::DIL_CATEGORY => [
            [
                'chosen_course', 'data_scelta_cpi', 'data_app_apl',
                'dote_activation_date', 'dote_expiry_date', 'subsidy_type',
            ],
            [
                'data_scelta_cpi', 'data_app_apl', 'dote_activation_date',
                'dote_expiry_date', 'subsidy_type', 'id_corso', self::COURSE_SITE,
            ],
        ],
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
     * resolves its own eight codes and nothing else, so what survives the
     * filter is exactly the client's own order: the two appointment dates, the
     * two DOTE ones, the remaining hours, the subsidy, the course references.
     *
     * A row holding only a code no previous revision resolved composes to the
     * same blob as before for every earlier effective set — which is why
     * "Residuo Ore" joined as a row of its own without a PREVIOUS_ROWS entry.
     *
     * @var list<list<string>>
     */
    public const array ROWS = [
        ['data_scelta_cpi', 'data_app_apl'],
        ['ora_app_cpi', 'ora_app_apl'],
        ['dote_activation_date', 'dote_expiry_date'],
        [self::DIL_REMAINING_HOURS],
        ['stato_assoc_cpi', 'dote_remaining_hours'],
        ['cpi', 'profilo_cpi'],
        ['subsidy_type'],
        ['id_corso', self::COURSE_SITE],
        ['gol_notice', 'application_window'],
        ['course_time_preference', 'price'],
        ['psp', 'did'],
        ['identity_documents', 'digital_identity'],
        ['foreign_user_documents', self::DEGREE_ATTRIBUTE],
    ];

    /**
     * ROWS as it stood BEFORE "Sede corso" joined it (user directive
     * 2026-09-10) — frozen history, never edited to follow ROWS.
     *
     * It is what lets the two layout seeders RECOGNISE the composition they
     * wrote on an installation provisioned by the previous revision, and
     * recompose it. Without it that installation would keep its old blob (a
     * configured layout is user data, never overwritten), the new attribute
     * would resolve but sit in no section, and the renderer would strand it in
     * the synthesized "Altre informazioni" — the field present but in the
     * wrong place, which is worse than absent.
     *
     * A future change to ROWS adds ITS predecessor here in turn, exactly as
     * QualificaQuoteLayoutSeeder::PREVIOUS_SECTIONS stacks its own.
     *
     * @var list<list<string>>
     */
    public const array PREVIOUS_ROWS = [
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
