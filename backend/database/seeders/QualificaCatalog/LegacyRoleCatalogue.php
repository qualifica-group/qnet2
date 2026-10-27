<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The QNet legacy roles turned into qnet-2 roles (user directive 2026-10-27):
 * only the legacy roles an active person still holds, merged by job profile
 * and translated onto the qnet-2 modules. Pure data, read by
 * QualificaLegacyRoleSeeder (the matrices and who holds them).
 *
 * A matrix is `resource => abilities`, resolved against the real permission
 * catalogue, so an ability that does not exist is never invented. "limited"
 * in the legacy (only one's own records) is the absence of the `viewAll` /
 * `viewSite` widenings here. Task, Segnatempo and Assenze come from
 * OperatorRoleCatalogue::BASE_ROLE, held next to these roles.
 */
final class LegacyRoleCatalogue
{
    /** Every ability the catalogue declares for the resource. */
    public const string ALL = '*';

    private const array READ = ['viewAny', 'view'];

    private const array WRITE = ['viewAny', 'view', 'create', 'update'];

    private const array WRITE_DELETE = ['viewAny', 'view', 'create', 'update', 'delete'];

    /**
     * The pickers of the opportunity and quote forms that are not for-selects
     * (as CommercialBackOfficeRoleCatalogue::SELECT_ONLY_RESOURCES).
     */
    private const array FORM_SELECTS = [
        'product-categories' => ['viewAny'],
        'operational-sites' => ['viewAny'],
        'tags' => ['viewAny'],
    ];

    public const string OPERATIONS_ROLE = 'coordinamento-operativo';

    public const string ACCOUNTING_ROLE = 'contabilita';

    public const string TRAINING_OFFICE_ROLE = 'segreteria-didattica';

    public const string SALES_CONSULTANT_ROLE = 'consulente-commerciale';

    public const string SOCIAL_MEDIA_ROLE = 'social-media-manager';

    public const string TECHNICIAN_ROLE = 'tecnico-consulenza';

    public const string TECHNICAL_MANAGER_ROLE = 'responsabile-tecnico';

    public const string PURCHASING_ROLE = 'ufficio-acquisti';

    public const string PURCHASE_APPROVAL_ROLE = 'autorizzazione-acquisti';

    public const string TIME_ENTRY_SUPERVISOR_ROLE = 'supervisore-segnatempo';

    public const string USER_OFFICE_ROLE = 'segreteria-utenti';

    /**
     * role name => its description (user-facing, Italian) and matrix.
     *
     * Only USER_OFFICE_ROLE writes users: whoever writes a user may assign
     * any role but super-admin (RoleAssignmentGuard), `admin` included.
     *
     * @var array<string, array{description: string, permissions: array<string, string|array<int, string>>}>
     */
    public const array ROLES = [
        // COP, "Coordinatori di settore e risorse", PIA and "Pianificazione
        // speciale" (the commessa planning is `contracts.program`).
        self::OPERATIONS_ROLE => [
            'description' => 'Coordinamento operativo e pianificazione',
            'permissions' => [
                'registries' => self::ALL,
                'referents' => self::ALL,
                'opportunities' => self::ALL,
                'quotes' => self::ALL,
                'work-orders' => self::ALL,
                'contracts' => self::ALL,
                'products' => self::ALL,
                'product-categories' => self::WRITE,
                'document-layouts' => self::WRITE,
                'document-bundles' => self::WRITE_DELETE,
                'task-templates' => self::WRITE,
                'tags' => self::WRITE,
                'enrollee-management' => self::READ,
                'users' => self::READ,
                'operational-sites' => ['viewAny'],
            ],
        ],
        // "CON _ Contabilita'", "Amministrazione" and CFO: the whole accounting
        // section, the commercial chain read in full, the stock and the
        // fulfilment of the purchase requests.
        self::ACCOUNTING_ROLE => [
            'description' => 'Contabilita\' e amministrazione',
            'permissions' => [
                'invoices' => self::ALL,
                'invoice-installments' => self::ALL,
                'invoice-tags' => self::ALL,
                'proforma-requests' => self::ALL,
                'purchase-invoices' => self::ALL,
                'purchase-invoice-installments' => self::ALL,
                'financial-accounts' => self::ALL,
                'payment-methods' => self::ALL,
                'ledger' => self::ALL,
                'work-order-payment-statuses' => self::ALL,
                'commission-accruals' => self::ALL,
                'commission-settlements' => self::ALL,
                'commission-configurations' => self::ALL,
                'agent-fiscal-profiles' => self::ALL,
                'enasarco-parameters' => self::ALL,
                'management-control' => self::ALL,
                'stock' => self::ALL,
                'registries' => self::WRITE,
                'referents' => self::WRITE,
                'opportunities' => [...self::WRITE_DELETE, 'export', 'viewAll'],
                'quotes' => [...self::WRITE_DELETE, 'viewAll'],
                'work-orders' => [...self::WRITE, 'export', 'viewAll', 'viewContractData', 'viewCosts', 'viewInvoices', 'managePayments', 'viewDocuments'],
                'contracts' => [...self::READ, 'viewAll', 'export'],
                'purchase-requests' => ['view', 'viewAll', 'fulfill'],
                'users' => self::READ,
                ...self::FORM_SELECTS,
            ],
        ],
        // "FOR_Segreteria Didattica", "Partner formazione gol" and "LAV_
        // Tecnico APL- PAL": the commercial chain on their own records and the
        // enrollees read.
        self::TRAINING_OFFICE_ROLE => [
            'description' => 'Segreteria didattica',
            'permissions' => [
                'registries' => self::WRITE,
                'referents' => self::WRITE,
                'opportunities' => self::WRITE_DELETE,
                'quotes' => self::WRITE,
                'work-orders' => self::WRITE,
                'contracts' => self::READ,
                'enrollee-management' => self::READ,
                ...self::FORM_SELECTS,
            ],
        ],
        // "COM_Internal Sales Consultant" and "COM_Settore Commerciale".
        self::SALES_CONSULTANT_ROLE => [
            'description' => 'Consulente commerciale',
            'permissions' => [
                'registries' => self::WRITE,
                'referents' => self::WRITE_DELETE,
                'sources' => self::WRITE,
                'opportunities' => [...self::WRITE_DELETE, 'export'],
                'quotes' => self::WRITE_DELETE,
                'work-orders' => self::WRITE_DELETE,
                'contracts' => self::READ,
                'products' => self::WRITE_DELETE,
                ...self::FORM_SELECTS,
                'product-categories' => self::WRITE,
            ],
        ],
        self::SOCIAL_MEDIA_ROLE => [
            'description' => 'Social media manager',
            'permissions' => [
                'registries' => self::WRITE,
                'referents' => ['viewAny', 'view', 'create'],
                'sources' => self::WRITE,
                'opportunities' => self::WRITE,
                'quotes' => ['viewAny', 'view', 'create'],
                'work-orders' => self::READ,
                'contracts' => self::READ,
                'document-layouts' => self::WRITE,
                'purchase-requests' => ['view', 'create', 'fulfill'],
                'stock' => self::ALL,
                ...self::FORM_SELECTS,
            ],
        ],
        // The legacy technicians: ISO, GDPR, Sicurezza, SOA Gare, Finanza
        // agevolata, PR Sedi.
        self::TECHNICIAN_ROLE => [
            'description' => 'Tecnico consulenza',
            'permissions' => [
                'registries' => self::WRITE,
                'referents' => self::WRITE_DELETE,
                'opportunities' => self::WRITE,
                'quotes' => self::WRITE,
                'work-orders' => self::WRITE,
                'contracts' => self::READ,
                'document-bundles' => self::WRITE,
                'email-templates' => self::WRITE,
                ...self::FORM_SELECTS,
            ],
        ],
        // The legacy "Responsabile" roles (Sicurezza, Istruzione, Fondi
        // interprofessionali) and the coordinator of Gare e Avvalimenti.
        self::TECHNICAL_MANAGER_ROLE => [
            'description' => 'Responsabile tecnico di settore',
            'permissions' => [
                'registries' => [...self::WRITE, 'export'],
                'referents' => self::WRITE_DELETE,
                'opportunities' => [...self::WRITE_DELETE, 'export'],
                'quotes' => self::WRITE_DELETE,
                'work-orders' => [...self::WRITE_DELETE, 'export'],
                'contracts' => [...self::READ, 'export', 'program'],
                'enrollee-management' => self::READ,
                'document-bundles' => self::WRITE,
                'email-templates' => self::WRITE,
                ...self::FORM_SELECTS,
            ],
        ],
        // The legacy "Ufficio Acquisti" read the requests; qnet-2 adds the
        // purchase orders, theirs to issue but not to approve.
        self::PURCHASING_ROLE => [
            'description' => 'Ufficio acquisti',
            'permissions' => [
                'purchase-requests' => ['view', 'viewAll', 'fulfill', 'export'],
                'purchase-orders' => ['view', 'create', 'update', 'delete', 'changeStatus', 'merge', 'receive', 'send', 'export', 'viewActivity'],
            ],
        ],
        self::PURCHASE_APPROVAL_ROLE => [
            'description' => 'Autorizzazione acquisti',
            'permissions' => [
                'purchase-requests' => ['view', 'viewAll', 'fulfill', 'close', 'delete'],
                'purchase-orders' => ['view', 'approve'],
            ],
        ],
        self::TIME_ENTRY_SUPERVISOR_ROLE => [
            'description' => 'Segnatempo di tutti (sola lettura)',
            'permissions' => [
                'time-entries' => [...self::READ, 'viewAll'],
            ],
        ],
        self::USER_OFFICE_ROLE => [
            'description' => 'Segreteria (gestione utenti)',
            'permissions' => [
                'users' => self::WRITE,
            ],
        ],
    ];

    /**
     * legacy role name (verbatim) => the qnet-2 roles it becomes. An empty
     * list is a legacy role with no qnet-2 counterpart: its holders keep the
     * base role only. The legacy roles no active person holds are absent.
     *
     * @var array<string, array<int, string>>
     */
    public const array LEGACY_ROLES = [
        'Admin' => [OperatorRoleCatalogue::ADMIN_ROLE],
        'COP_ Coordinamento Operativo e Pianificazione' => [self::OPERATIONS_ROLE],
        'COORDINATORI DI SETTORE E RISORSE' => [self::OPERATIONS_ROLE],
        'PIA _ Pianificazione' => [self::OPERATIONS_ROLE],
        'Pianificazione Speciale' => [self::OPERATIONS_ROLE],
        'CON _ Contabilità' => [self::ACCOUNTING_ROLE],
        'Amministrazione' => [self::ACCOUNTING_ROLE],
        'CFO' => [self::ACCOUNTING_ROLE],
        'FOR_Segreteria Didattica' => [self::TRAINING_OFFICE_ROLE],
        'Partner formazione gol' => [self::TRAINING_OFFICE_ROLE],
        'LAV_ Tecnico APL- PAL' => [self::TRAINING_OFFICE_ROLE],
        'COM_Internal Sales Consultant' => [self::SALES_CONSULTANT_ROLE],
        'COM_Settore Commerciale' => [self::SALES_CONSULTANT_ROLE],
        'COM_Social Media Manager' => [self::SOCIAL_MEDIA_ROLE],
        'LAV _ Tecnico Iso' => [self::TECHNICIAN_ROLE],
        'LAV _ Tecnico Gdpr' => [self::TECHNICIAN_ROLE],
        'LAV _ Tecnico Sicurezza' => [self::TECHNICIAN_ROLE],
        'LAV _ Tecnico della Sicurezza sul Lavoro' => [self::TECHNICIAN_ROLE],
        'LAV _ Tecnico SOA Gare' => [self::TECHNICIAN_ROLE],
        'LAV_ Tecnico Finanza Agevolata' => [self::TECHNICIAN_ROLE],
        'LAV_ Tecnico PR Sedi' => [self::TECHNICIAN_ROLE],
        'LAV - R _ Responsabile Tecnico Settore Sicurezza' => [self::TECHNICAL_MANAGER_ROLE],
        'LAV - R _ ISTR - Responsabile Istruzione' => [self::TECHNICAL_MANAGER_ROLE],
        'LAV - R _ FON - Responsabile Tecnico Fondi Interprofessionali' => [self::TECHNICAL_MANAGER_ROLE],
        'LAV_Coordinatore Reparto Gare_Avvalimenti' => [self::TECHNICAL_MANAGER_ROLE],
        'Ufficio Acquisti' => [self::PURCHASING_ROLE],
        'Ufficio Acquisti (Autorizzazione)' => [self::PURCHASING_ROLE, self::PURCHASE_APPROVAL_ROLE],
        'Vedi tutti i segnatempo' => [self::TIME_ENTRY_SUPERVISOR_ROLE],
        'SEG_Segreteria' => [self::USER_OFFICE_ROLE],
        // Task-only in the legacy: the base role already covers it.
        'Gestione Ticket' => [],
        'COM_Web Master / SEO Specialist' => [],
        // No qnet-2 module: the non-conformity register, the manual upload.
        'Registro NC' => [],
        'Upload Manuale Operativo' => [],
    ];
}
