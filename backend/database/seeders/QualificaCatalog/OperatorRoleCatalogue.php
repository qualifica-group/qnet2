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

    /** "Gestione Iscritti: sola visualizzazione sulle Sedi abilitate". */
    public const string SITE_ENROLLEES_READ = 'site-enrollees-read';

    public const string REQUEST_MODULE = 'request-management';

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
                self::MARKETING, self::ALL_REQUESTS, self::FIELD_CHANGE_REVIEW,
                self::CATALOG_AND_REGISTRIES, self::STATUS_CONFIGURATOR, self::REWARDS, self::ALL_ENROLLEES,
            ],
        ],
        // The CSV's "( no richieste modifica )", and no status configurator nor
        // rewards: the supervisor minus those. Holding `updateSource`, they
        // have nothing to propose.
        self::COORDINATOR_ROLE => [
            'description' => 'Coordinatore commerciale',
            'blocks' => [self::MARKETING, self::ALL_REQUESTS, self::CATALOG_AND_REGISTRIES, self::ALL_ENROLLEES],
        ],
        self::MARKETING_ROLE => [
            'description' => 'Marketing',
            'blocks' => [self::MARKETING],
        ],
        self::COMMERCIAL_ROLE => [
            'description' => 'Commerciale',
            'blocks' => [self::OWN_REQUESTS],
        ],
        self::ENROLLEE_COMMERCIAL_ROLE => [
            'description' => 'Commerciale con Gestione Iscritti',
            'blocks' => [self::OWN_REQUESTS, self::SITE_ENROLLEES_READ],
        ],
        // "Abilitazione a visionare tutti i dati delle sedi Lazio sia per
        // Richieste che per Iscritti": the commercial matrix widened to the
        // Sedi of the profile on both modules.
        self::TEACHING_SUPERVISOR_ROLE => [
            'description' => 'Supervisore didattica',
            'blocks' => [self::OWN_REQUESTS, self::SITE_REQUESTS, self::SITE_ENROLLEES_READ],
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
     * Grants on OTHER modules a request-management work panel needs beyond
     * their `viewAny` (user directive 2026-07-31):
     *  - `referents.create`: the "Segnalatore" quick-create "+" (spec 0028);
     *  - `notes.create`: the notes composer (spec 0052, D-6);
     *  - `attachments.*`: the Documents tab opened by `viewDocuments` is served
     *    by the polymorphic subsystem's own permissions.
     *
     * @var array<int, string>
     */
    public const array REQUEST_EXTRA_PERMISSIONS = [
        'referents.create',
        'notes.create',
        'attachments.viewAny',
        'attachments.view',
        'attachments.create',
        'attachments.delete',
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
     * Read-only on the enrollees of the Sedi the profile belongs to: no write,
     * no viewAll — `viewSite` is the whole reach (spec 0105 tier 3, applied to
     * the enrollee module by spec 0130).
     *
     * @var array<int, string>
     */
    public const array SITE_ENROLLEES_READ_ABILITIES = [
        'viewAny',
        'view',
        'viewSite',
    ];
}
