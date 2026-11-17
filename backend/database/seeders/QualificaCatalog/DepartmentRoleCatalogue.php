<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The department roles (spec 0408): two per department, `reparto-<key>` and
 * `reparto-<key>-senior`, composed from the grant keys of
 * DepartmentPermissionGrants as the department answered its questionnaire.
 * Today the senior role holds the base grants plus `senior_grants` (empty but
 * for Finanza Agevolata, D-1): the two levels diverge by editing this file.
 */
final class DepartmentRoleCatalogue
{
    public const string ROLE_PREFIX = 'reparto-';

    public const string SENIOR_SUFFIX = '-senior';

    public const string SENIOR_DESCRIPTION_SUFFIX = ' (senior)';

    public const string ISO = 'reparto-iso';

    public const string SAFETY = 'reparto-sicurezza';

    public const string TRAVEL = 'reparto-travel';

    public const string ACCREDITATION = 'reparto-accreditamenti';

    public const string SUBSIDISED_FINANCE = 'reparto-finanza-agevolata';

    public const string HUMAN_RESOURCES = 'reparto-hr';

    public const string TENDERS = 'reparto-gare';

    public const string MARKETING = 'reparto-marketing';

    public const string SOA = 'reparto-soa';

    /** The grant whose role carries the HR office field matrix (D-7). */
    public const string HR_OFFICE_GRANT = 'hr_office';

    /**
     * The mansione roles a listed person loses (D-5); every other role they
     * hold (cross-department, notifications) stays.
     *
     * @var array<int, string>
     */
    public const array REPLACED_ROLES = [
        'operatore-base',
        'tecnico-consulenza',
        'tecnico-consulenza-senza-costi',
        'coordinamento-operativo',
        'responsabile-tecnico',
        'risorse-umane',
        'marketing',
        'social-media-manager',
        'accesso anagrafiche opportunita commesse',
        'admin',
    ];

    /**
     * Accreditamenti, Gare-Avvalimenti and SOA answered the questionnaire the
     * same way.
     *
     * @var array<int, string>
     */
    private const array TECHNICAL_OFFICE_GRANTS = [
        'registries_view', 'registries_edit', 'registries_documents', 'registries_transfer',
        'opportunities_view', 'opportunities_documents',
        'work_orders_view', 'work_orders_economics', 'work_orders_manage_costs', 'work_orders_invoices',
        'work_orders_documents', 'work_orders_emails', 'work_orders_transfer', 'work_orders_history',
        'proforma_view', 'proforma_request', 'invoices_send',
        'purchase_requests_view',
        'requests_view',
        'trainings_own',
    ];

    /**
     * Finanza Agevolata "SOLO COMMESSE" (base level).
     *
     * @var array<int, string>
     */
    private const array SUBSIDISED_FINANCE_WORK_ORDER_GRANTS = [
        'registries_view',
        'work_orders_view', 'work_orders_view_competence', 'work_orders_edit', 'work_orders_economics',
        'work_orders_manage_costs', 'work_orders_invoices', 'work_orders_payments', 'work_orders_documents',
        'work_orders_emails', 'work_orders_delete', 'work_orders_transfer', 'work_orders_history',
        'tasks_edit', 'tasks_validate', 'tasks_delete', 'tasks_transfer',
        'proforma_request',
        'trainings_own',
    ];

    /**
     * @var array<string, array{description: string, grants: array<int, string>, senior_grants: array<int, string>}>
     */
    public const array DEPARTMENTS = [
        self::ISO => [
            'description' => 'Reparto ISO/GDPR',
            'grants' => [
                'registries_view', 'registries_documents',
                'work_orders_view', 'work_orders_documents', 'work_orders_emails',
                'tasks_edit', 'tasks_validate', 'tasks_delete',
                'purchase_requests_view', 'purchase_requests_create',
            ],
            'senior_grants' => [],
        ],
        self::SAFETY => [
            'description' => 'Reparto Sicurezza',
            'grants' => [
                'registries_view', 'registries_documents',
                'opportunities_view', 'opportunities_documents', 'opportunities_history',
                'quotes_view',
                'work_orders_view', 'work_orders_view_competence', 'work_orders_economics', 'work_orders_invoices',
                'work_orders_documents', 'work_orders_emails', 'work_orders_history',
                'tasks_edit', 'tasks_validate', 'tasks_transfer',
                'proforma_view', 'proforma_request', 'invoices_view',
                'purchase_requests_view',
            ],
            'senior_grants' => [],
        ],
        self::TRAVEL => [
            'description' => 'Reparto Travel',
            'grants' => [
                'registries_view', 'registries_edit', 'registries_documents', 'registries_delete',
                'leads_view', 'leads_edit', 'leads_import',
                'opportunities_view', 'opportunities_edit', 'opportunities_documents', 'opportunities_history',
                'quotes_view', 'quotes_edit', 'quotes_transfer', 'quotes_history',
                'work_orders_view', 'work_orders_edit', 'work_orders_economics', 'work_orders_manage_costs',
                'work_orders_invoices', 'work_orders_documents', 'work_orders_emails', 'work_orders_history',
                'contracts_view', 'contracts_change_status', 'contracts_history',
                'tasks_edit',
                'proforma_request', 'invoices_view', 'invoices_send',
                'purchase_requests_view', 'purchase_requests_create', 'purchase_orders', 'purchase_invoices_view',
                'request_statistics', 'requests_view', 'requests_import',
                'products_view', 'products_edit',
                'trainings_own',
            ],
            'senior_grants' => [],
        ],
        self::ACCREDITATION => [
            'description' => 'Reparto Accreditamenti e Gestione Sedi',
            'grants' => self::TECHNICAL_OFFICE_GRANTS,
            'senior_grants' => [],
        ],
        self::SUBSIDISED_FINANCE => [
            'description' => 'Reparto Finanza Agevolata, Istruzione e Progettazione',
            'grants' => self::SUBSIDISED_FINANCE_WORK_ORDER_GRANTS,
            'senior_grants' => [
                'registries_edit', 'registries_documents', 'registries_delete', 'registries_transfer', 'registries_history',
                'opportunities_view', 'opportunities_view_competence', 'opportunities_edit', 'opportunities_delete',
                'opportunities_export', 'opportunities_import', 'opportunities_documents', 'opportunities_history',
                'quotes_view', 'quotes_view_competence', 'quotes_edit', 'quotes_delete', 'quotes_transfer', 'quotes_history',
                'contracts_view', 'contracts_view_competence', 'contracts_validate', 'contracts_program',
                'contracts_change_status', 'contracts_terminate', 'contracts_reactivate', 'contracts_export', 'contracts_history',
                'purchase_requests_view', 'purchase_requests_create', 'purchase_requests_fulfill', 'purchase_orders', 'purchases_export',
                'request_statistics', 'requests_view', 'requests_import', 'enrollees_view', 'rewarded_referents',
                'products_view', 'products_edit', 'products_delete', 'products_transfer', 'stock_view',
                'users_view',
            ],
        ],
        self::HUMAN_RESOURCES => [
            'description' => 'Reparto HR',
            'grants' => [
                'registries_view', 'registries_edit',
                'opportunities_view', 'opportunities_edit', 'opportunities_delete', 'opportunities_export',
                'opportunities_import', 'opportunities_documents', 'opportunities_history',
                'quotes_view', 'quotes_edit', 'quotes_delete', 'quotes_transfer', 'quotes_history',
                'work_orders_view', 'work_orders_economics', 'work_orders_manage_costs', 'work_orders_invoices',
                'work_orders_payments', 'work_orders_documents', 'work_orders_emails', 'work_orders_delete',
                'work_orders_transfer', 'work_orders_history',
                'contracts_view',
                'tasks_view_all', 'tasks_edit', 'tasks_manage_all', 'tasks_validate', 'tasks_delete', 'tasks_transfer',
                'proforma_request',
                'purchase_requests_view', 'purchase_requests_create',
                'management_control', 'agent_sales_report', 'request_statistics',
                self::HR_OFFICE_GRANT,
            ],
            'senior_grants' => [],
        ],
        self::TENDERS => [
            'description' => 'Reparto Gare e Avvalimenti',
            'grants' => self::TECHNICAL_OFFICE_GRANTS,
            'senior_grants' => [],
        ],
        self::MARKETING => [
            'description' => 'Reparto Marketing',
            'grants' => [
                'leads_view', 'leads_edit', 'leads_import', 'campaigns_manage', 'leads_export',
                'opportunities_view',
                'tasks_transfer',
                'requests_view',
                'users_view',
            ],
            'senior_grants' => [],
        ],
        self::SOA => [
            'description' => 'Reparto SOA',
            'grants' => self::TECHNICAL_OFFICE_GRANTS,
            'senior_grants' => [],
        ],
    ];

    public static function senior(string $role): string
    {
        return $role.self::SENIOR_SUFFIX;
    }
}
