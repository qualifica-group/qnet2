<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The permission matrices of the client's mansioni (user directive
 * 2026-09-15, "Mansionario Operatori"), as composable BUILDING BLOCKS: a role
 * is the union of its blocks, resolved against the real permission catalogue
 * by QualificaRoleSeeder. Pure data, no logic.
 *
 * The matrices mirror the CSV's "moduli che puo' vedere" column literally
 * (user decision 2026-09-15): the supervisor no longer receives "everything
 * but administration", only the modules the mansionario lists for it.
 */
final class OperatorRoleCatalogue
{
    public const string SUPERVISOR_ROLE = 'supervisore-commerciale';

    public const string COORDINATOR_ROLE = 'coordinatore-commerciale';

    public const string MARKETING_ROLE = 'marketing';

    public const string COMMERCIAL_ROLE = 'commerciale';

    public const string ENROLLEE_COMMERCIAL_ROLE = 'commerciale-iscritti';

    public const string TEACHING_SUPERVISOR_ROLE = 'supervisore-didattica';

    /**
     * The English role names the former TestUsersSeeder created, replaced by
     * the Italian ones above (user directive 2026-09-15). Deleted on every run
     * so an already-seeded installation does not keep them as empty duplicates.
     * `marketing` is absent: its name did not change.
     *
     * @var array<int, string>
     */
    public const array RETIRED_ROLES = [
        'supervisor',
        'commercial',
    ];

    /** "Marketing e Lead": its modules in full plus the selects they read. */
    public const string MARKETING = 'marketing';

    /** Lead -> Opportunita' conversion, without the Opportunita' module itself. */
    public const string LEAD_CONVERSION = 'lead-conversion';

    /** "Gestione Richieste" unrestricted: every request, report included. */
    public const string ALL_REQUESTS = 'all-requests';

    /** "Gestione Richieste: accesso ai soli contatti che gestiscono", no report. */
    public const string OWN_REQUESTS = 'own-requests';

    /** Tier-3 visibility (spec 0105): the requests of the user's own Sedi. */
    public const string SITE_REQUESTS = 'site-requests';

    /** "prodotti + categorie prodotti + anagrafiche + referenti", viewed and edited. */
    public const string CATALOG_AND_REGISTRIES = 'catalog-and-registries';

    /** "configuratore di stati": the offer status configurator. */
    public const string STATUS_CONFIGURATOR = 'status-configurator';

    /** "buoni e incentivi": the whole "Premi e Incentivi" navigation group. */
    public const string REWARDS = 'rewards';

    /** The "Richieste di modifica" page: review and approval. */
    public const string FIELD_CHANGE_REVIEW = 'field-change-review';

    /** "gestione iscritti" unrestricted: the whole enrollee module. */
    public const string ALL_ENROLLEES = 'all-enrollees';

    /**
     * "Gestione Iscritti" read-only, reach limited to the offers the user
     * operates (user directive 2026-09-18). SITE_ENROLLEES widens it.
     */
    public const string ENROLLEES_READ = 'enrollees-read';

    /** Tier-3 visibility on Gestione Iscritti: the enrollees of the user's own Sedi. */
    public const string SITE_ENROLLEES = 'site-enrollees';

    /**
     * The "Utenti" and "Ruoli" administration sections, every ability but
     * `create` (user directive 2026-09-18).
     */
    public const string USERS_AND_ROLES = 'users-and-roles';

    /**
     * The Task module reduced to the Tasks the user takes part in: no
     * `viewAll`/`viewSite` widening, no `manageAll` ("Gestore").
     */
    public const string OWN_TASKS = 'own-tasks';

    /** The Segnatempo reduced to the user's own entries: no admin, no team view. */
    public const string OWN_TIME_ENTRIES = 'own-time-entries';

    /**
     * The blocks every mansione holds on top of its own (user directive
     * 2026-09-24: "tutti gli utenti abilitati a task e segnatempo, task
     * propri non tutti i task").
     *
     * @var array<int, string>
     */
    public const array EVERY_ROLE_BLOCKS = [self::OWN_TASKS, self::OWN_TIME_ENTRIES];

    public const string REQUEST_MODULE = 'request-management';

    public const string TASK_MODULE = 'tasks';

    public const string TIME_ENTRY_MODULE = 'time-entries';

    public const string ENROLLEE_MODULE = 'enrollee-management';

    public const string FIELD_CHANGE_MODULE = 'field-change-requests';

    /**
     * The only `field-change-requests.*` grant of a role scoped to its own
     * requests (spec 0078, D-1): lacking `request-management.updateSource`,
     * they PROPOSE a change to the Fonte instead of writing it. `.view` stays
     * out on purpose — the navigation entry is gated on it, and the page is
     * supervisor-only (user directive 2026-08-04).
     */
    public const string FIELD_CHANGE_PROPOSAL = 'field-change-requests.create';

    /**
     * role name => its description (user-facing, Italian) and building blocks.
     *
     * @var array<string, array{description: string, blocks: array<int, string>}>
     */
    public const array ROLES = [
        self::SUPERVISOR_ROLE => [
            'description' => 'Supervisore commerciale',
            'blocks' => [
                self::MARKETING, self::LEAD_CONVERSION, self::ALL_REQUESTS, self::FIELD_CHANGE_REVIEW,
                self::CATALOG_AND_REGISTRIES, self::STATUS_CONFIGURATOR, self::REWARDS, self::ALL_ENROLLEES,
                self::USERS_AND_ROLES,
            ],
        ],
        // The CSV's "( no richieste modifica )", and no status configurator nor
        // rewards: the supervisor minus those. Holding `updateSource`, they
        // have nothing to propose.
        self::COORDINATOR_ROLE => [
            'description' => 'Coordinatore commerciale',
            'blocks' => [self::MARKETING, self::LEAD_CONVERSION, self::ALL_REQUESTS, self::CATALOG_AND_REGISTRIES, self::ALL_ENROLLEES],
        ],
        self::MARKETING_ROLE => [
            'description' => 'Marketing',
            'blocks' => [self::MARKETING, self::LEAD_CONVERSION],
        ],
        self::COMMERCIAL_ROLE => [
            'description' => 'Commerciale',
            'blocks' => [self::OWN_REQUESTS, self::ENROLLEES_READ],
        ],
        self::ENROLLEE_COMMERCIAL_ROLE => [
            'description' => 'Commerciale con Gestione Iscritti',
            'blocks' => [self::OWN_REQUESTS, self::ENROLLEES_READ, self::SITE_ENROLLEES],
        ],
        // "Abilitazione a visionare tutti i dati delle sedi Lazio sia per
        // Richieste che per Iscritti": the commercial matrix widened to the
        // Sedi of the profile on both modules.
        self::TEACHING_SUPERVISOR_ROLE => [
            'description' => 'Supervisore didattica',
            'blocks' => [self::OWN_REQUESTS, self::SITE_REQUESTS, self::ENROLLEES_READ, self::SITE_ENROLLEES],
        ],
    ];

    /**
     * The modules of the "Marketing e Lead" navigation group: projects,
     * campaigns, leads (the `leads.import` wizard included, it is a lead
     * ability) and the pipeline-status pick-list those two classify against.
     *
     * @var array<int, string>
     */
    public const array MARKETING_MODULES = [
        'projects',
        'campaigns',
        'leads',
        'pipeline-statuses',
    ];

    /**
     * LEAD_CONVERSION (user directive 2026-09-16: every operator of the three
     * marketing roles converts leads). Both conversion paths gate on
     * `opportunities.create`; the single-lead one opens the Opportunity form,
     * whose `GET /meta/opportunities` needs `viewAny`. `view` stays out: the
     * Opportunita' menu entry is gated on it, and the module is not theirs.
     *
     * @var array<int, string>
     */
    public const array LEAD_CONVERSION_PERMISSIONS = [
        'opportunities.viewAny',
        'opportunities.create',
    ];

    /**
     * Resources feeding the relation selects of the project, campaign and lead
     * forms. `viewAny` only: the selects answer while the menu entries, gated
     * on `<resource>.view`, stay hidden.
     *
     * @var array<int, string>
     */
    public const array MARKETING_SELECT_ONLY_RESOURCES = [
        'business-functions',
        'referents',
        'product-categories',
        'operational-sites',
        'registries',
        'sources',
        'users',
    ];

    /**
     * The modules of CATALOG_AND_REGISTRIES, granted in full (view AND edit,
     * Mansionario 2026-09-15).
     *
     * @var array<int, string>
     */
    public const array CATALOG_AND_REGISTRIES_MODULES = [
        'products',
        'product-categories',
        'registries',
        'referents',
    ];

    /**
     * Pick-lists the product, registry and referent forms read from: `viewAny`
     * only, so their own menu entries (Configurazione / Anagrafiche) stay hidden.
     *
     * @var array<int, string>
     */
    public const array CATALOG_AND_REGISTRIES_SELECT_ONLY_RESOURCES = [
        'attributes',
        'vat-rates',
        'units-of-measure',
        'product-typologies',
        'referent-types',
        'sectors',
        'tags',
        'companies',
    ];

    /**
     * USERS_AND_ROLES: the administration modules it opens.
     *
     * @var array<int, string>
     */
    public const array USERS_AND_ROLES_MODULES = [
        'users',
        'roles',
    ];

    /**
     * The abilities USERS_AND_ROLES withholds on its modules.
     *
     * @var array<int, string>
     */
    public const array USERS_AND_ROLES_DENIED_ABILITIES = [
        'create',
    ];

    /**
     * STATUS_CONFIGURATOR: "Configuratore Stati Offerta" in the navigation.
     *
     * @var array<int, string>
     */
    public const array STATUS_CONFIGURATOR_MODULES = [
        'quote-workflows',
    ];

    /**
     * REWARDS: the "Premi e Incentivi" navigation group in full.
     *
     * @var array<int, string>
     */
    public const array REWARDS_MODULES = [
        'reward-types',
        'reward-statuses',
        'rewarded-referents',
    ];

    /**
     * The collaboration tabs a request work panel and a Task detail share:
     *  - `notes.create`: the notes composer (spec 0052, D-6);
     *  - `attachments.*`: the Documents tab opened by `viewDocuments` is served
     *    by the polymorphic subsystem's own permissions.
     *
     * @var array<int, string>
     */
    public const array COLLABORATION_PERMISSIONS = [
        'notes.create',
        'attachments.viewAny',
        'attachments.view',
        'attachments.create',
        'attachments.delete',
    ];

    /**
     * Grants on OTHER modules a request-management work panel needs beyond
     * their `viewAny` (user directive 2026-07-31): the collaboration tabs,
     * plus `referents.create` for the "Segnalatore" quick-create "+" (spec
     * 0028).
     *
     * @var array<int, string>
     */
    public const array REQUEST_EXTRA_PERMISSIONS = [
        'referents.create',
        ...self::COLLABORATION_PERMISSIONS,
    ];

    /**
     * OWN_TASKS: the abilities that would reach beyond the Tasks the user
     * takes part in — the two visibility widenings (TaskVisibilityScope) and
     * "Gestore" over every Task.
     *
     * @var array<int, string>
     */
    public const array OWN_TASKS_DENIED_ABILITIES = [
        'viewAll',
        'viewSite',
        'manageAll',
    ];

    /**
     * OWN_TIME_ENTRIES: the abilities over other users' segnatempo (spec
     * 0122, D-8) — `manageAll` (admin), `viewAll` (whole-structure team view)
     * and the monthly export ("Creatore NO, Admin SI'").
     *
     * @var array<int, string>
     */
    public const array OWN_TIME_ENTRIES_DENIED_ABILITIES = [
        'manageAll',
        'viewAll',
        'exportMonthly',
    ];

    /**
     * Selects of the request-management work panel and create form for an
     * unrestricted role. `operational-sites` is included: the ceiling of
     * `operational_site_id` hangs off `operational-sites.viewAny`, and the
     * Sede is theirs to decide.
     *
     * @var array<int, string>
     */
    public const array ALL_REQUESTS_SELECT_ONLY_RESOURCES = [
        'registries',
        'sources',
        'referents',
        'product-categories',
        'operational-sites',
        'business-functions',
        'users',
    ];

    /**
     * Same selects for a role scoped to its own requests. `operational-sites`
     * is deliberately absent (user directive 2026-08-03): its absence is what
     * locks the Sede operativa even on creation, where the per-field matrix
     * does not reach. `product-categories` reads the structural tree gated by
     * ProductCategoryPolicy::viewAny.
     *
     * @var array<int, string>
     */
    public const array OWN_REQUESTS_SELECT_ONLY_RESOURCES = [
        'registries',
        'sources',
        'referents',
        'product-categories',
        'users',
    ];

    /**
     * The request-management abilities a role scoped to its own requests must
     * NOT hold:
     *  - `delete` (user directive 2026-07-31);
     *  - `viewAll` / `viewSite`: they see ONLY the requests they operate —
     *    either grant widens the D-3 scoping (RequestManagementScope);
     *  - `updateSource` (spec 0078, D-1): they propose the Fonte instead;
     *  - `assignOperator` (user directive 2026-08-03): the create form's
     *    Operatore and the bulk assign endpoint;
     *  - `report` (Mansionario 2026-09-15, "no report").
     *
     * `appendTeamMember` stays granted (user directive 2026-09-08), paired with
     * the read-only `manager_slots` below.
     *
     * @var array<int, string>
     */
    public const array OWN_REQUESTS_DENIED_ABILITIES = [
        'delete',
        'viewAll',
        'viewSite',
        'updateSource',
        'assignOperator',
        'report',
    ];

    /**
     * Spec 0006 field matrix of the roles scoped to their own requests: the
     * Sede operativa is supervisory attribution, decided FOR them (user
     * directive 2026-08-03).
     *
     * @var array<string, array<int, string>>
     */
    public const array OWN_REQUESTS_HIDDEN_FIELDS = [
        self::REQUEST_MODULE => ['operational_site_id'],
    ];

    /**
     * Visible but locked (direttiva utente 2026-09-08): the squadra is readable
     * and `appendTeamMember` lets them add to its tail only. The two halves
     * must be seeded together or the grant does nothing.
     *
     * @var array<string, array<int, string>>
     */
    public const array OWN_REQUESTS_READONLY_FIELDS = [
        self::REQUEST_MODULE => ['manager_slots'],
    ];

    /**
     * ENROLLEES_READ: no write, and no `viewAll`/`viewSite` — without them
     * RequestManagementScope narrows the module to the offers the user
     * operates (tier 2).
     *
     * @var array<int, string>
     */
    public const array ENROLLEES_READ_ABILITIES = [
        'viewAny',
        'view',
    ];
}
