<?php

namespace Database\Seeders\QualificaCatalog;

use Database\Seeders\QualificaCatalog\DepartmentRoleCatalogue as Department;

/**
 * Each department's questionnaire answers as grant keys of
 * DepartmentPermissionGrants (spec 0408 D-2), kept apart from
 * DepartmentRoleCatalogue so the role list stays readable.
 */
final class DepartmentGrantSets
{
    /** ISO/GDPR, as its technicians answered. */
    public const array ISO = [
        'registries_view', 'registries_documents',
        'work_orders_view', 'work_orders_documents', 'work_orders_emails',
        'tasks_edit', 'tasks_validate', 'tasks_delete',
        'purchase_requests_view', 'purchase_requests_create',
    ];

    /**
     * ISO/GDPR note "UFFICIO PINIFICAZIONE E RESP SI": what the planning office
     * and the head of the department hold on top of the technicians.
     */
    public const array PLANNING = [
        'registries_edit', 'registries_delete', 'registries_transfer', 'registries_history',
        'opportunities_view', 'opportunities_view_competence', 'opportunities_edit', 'opportunities_export',
        'opportunities_documents', 'opportunities_history',
        'quotes_view', 'quotes_view_competence', 'quotes_edit', 'quotes_delete', 'quotes_transfer', 'quotes_history',
        'work_orders_view_competence', 'work_orders_edit', 'work_orders_economics', 'work_orders_manage_costs',
        'work_orders_invoices', 'work_orders_payments', 'work_orders_delete', 'work_orders_transfer', 'work_orders_history',
        'contracts_view', 'contracts_view_competence', 'contracts_validate', 'contracts_program', 'contracts_change_status',
        'contracts_terminate', 'contracts_reactivate', 'contracts_export', 'contracts_history',
        'tasks_transfer',
        'proforma_view', 'proforma_request', 'invoices_view', 'invoices_export',
        'purchase_invoices_view', 'commission_accruals_view',
    ];

    public const array SAFETY = [
        'registries_view', 'registries_documents',
        'opportunities_view', 'opportunities_documents', 'opportunities_history',
        'quotes_view',
        'work_orders_view', 'work_orders_view_competence', 'work_orders_economics', 'work_orders_invoices',
        'work_orders_documents', 'work_orders_emails', 'work_orders_history',
        'tasks_edit', 'tasks_validate', 'tasks_transfer',
        'proforma_view', 'proforma_request', 'invoices_view',
        'purchase_requests_view',
    ];

    /** Sicurezza: "solo responsabile" / "SOLO FERNANDO SI". */
    public const array SAFETY_HEAD = [
        'work_orders_transfer', 'commission_accruals_view', 'commission_settlements_view', 'commissions_export',
    ];

    public const array TRAVEL = [
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
    ];

    /** Accreditamenti, Gare-Avvalimenti and SOA answered the same way. */
    public const array TECHNICAL_OFFICE = [
        'registries_view', 'registries_edit', 'registries_documents', 'registries_transfer',
        'opportunities_view', 'opportunities_documents',
        'work_orders_view', 'work_orders_economics', 'work_orders_manage_costs', 'work_orders_invoices',
        'work_orders_documents', 'work_orders_emails', 'work_orders_transfer', 'work_orders_history',
        'proforma_view', 'proforma_request', 'invoices_send',
        'purchase_requests_view',
        'requests_view',
        'trainings_own',
    ];

    /** Finanza Agevolata "SOLO COMMESSE" (base level). */
    public const array SUBSIDISED_FINANCE = [
        'registries_view',
        'work_orders_view', 'work_orders_view_competence', 'work_orders_edit', 'work_orders_economics',
        'work_orders_manage_costs', 'work_orders_invoices', 'work_orders_payments', 'work_orders_documents',
        'work_orders_emails', 'work_orders_delete', 'work_orders_transfer', 'work_orders_history',
        'tasks_edit', 'tasks_validate', 'tasks_delete', 'tasks_transfer',
        'proforma_request',
        'trainings_own',
    ];

    /** Finanza Agevolata "livello alto / tutto il settore". */
    public const array SUBSIDISED_FINANCE_SENIOR = [
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
    ];

    public const array HUMAN_RESOURCES = [
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
        Department::HR_OFFICE_GRANT,
    ];

    public const array MARKETING = [
        'leads_view', 'leads_edit', 'leads_import', 'campaigns_manage', 'leads_export',
        'opportunities_view',
        'tasks_transfer',
        'requests_view',
        'users_view',
    ];

    /**
     * APL: every opportunity, quotes and commesse of their own Sede, tasks of
     * their Sede (managing others' goes through the org chart, D-4), the whole
     * active invoicing, requests they are involved in. Contracts answered
     * "Non so" (definitions to agree): none yet.
     */
    public const array APL = [
        'registries_view', 'registries_edit', 'registries_documents', 'registries_delete', 'registries_transfer',
        'registries_history',
        'opportunities_view', 'opportunities_view_all', 'opportunities_edit', 'opportunities_delete',
        'opportunities_export', 'opportunities_import', 'opportunities_documents', 'opportunities_history',
        'quotes_view', 'quotes_view_site', 'quotes_edit', 'quotes_delete', 'quotes_transfer', 'quotes_history',
        'work_orders_view', 'work_orders_view_site', 'work_orders_edit', 'work_orders_economics',
        'work_orders_manage_costs', 'work_orders_invoices', 'work_orders_payments', 'work_orders_documents',
        'work_orders_emails', 'work_orders_delete', 'work_orders_transfer', 'work_orders_history',
        'tasks_view_site', 'tasks_edit', 'tasks_validate', 'tasks_delete', 'tasks_transfer',
        'proforma_view', 'proforma_request', 'invoices_view', 'invoices_edit', 'invoices_collect', 'invoices_send',
        'invoices_delete', 'invoices_export',
        'request_statistics', 'requests_view', 'requests_import', 'enrollees_view', 'rewarded_referents',
        'users_view',
    ];

    /** APL: "Luca Chiappetti, deve invece vedere tutto" (read, on top of the base). */
    public const array APL_SENIOR = [
        'quotes_view_all', 'work_orders_view_all', 'contracts_view', 'contracts_view_all', 'tasks_view_all',
        'requests_view_all',
    ];

    /** Contabilita': sees every record, acts on invoicing, purchases and treasury. */
    public const array ACCOUNTING = [
        'registries_view', 'registries_documents', 'registries_transfer', 'registries_history',
        'leads_view', 'leads_import', 'campaigns_manage', 'leads_export',
        'opportunities_view', 'opportunities_view_all', 'opportunities_export', 'opportunities_import',
        'opportunities_documents', 'opportunities_history',
        'quotes_view', 'quotes_view_all', 'quotes_transfer', 'quotes_history',
        'work_orders_view', 'work_orders_view_all', 'work_orders_economics', 'work_orders_invoices',
        'work_orders_payments', 'work_orders_documents', 'work_orders_emails', 'work_orders_history',
        'contracts_view', 'contracts_view_all', 'contracts_export', 'contracts_history',
        'tasks_view_all', 'tasks_transfer',
        'proforma_view', 'proforma_request', 'invoices_view', 'invoices_edit', 'invoices_collect', 'invoices_send',
        'invoices_delete', 'invoices_export',
        'purchase_requests_view', 'purchase_requests_view_all', 'purchase_requests_create', 'purchase_orders',
        'purchase_invoices_view', 'purchase_invoices_edit', 'purchase_invoices_pay', 'purchases_export',
        'purchase_invoices_export',
        'commission_accruals_view', 'agent_fiscal_profiles_view', 'commission_settlements_view', 'commissions_export',
        'treasury_view', 'treasury_edit', 'treasury_reconcile', 'ledger_view', 'payment_cards_reveal', 'treasury_export',
        'management_control', 'agent_sales_report', 'request_statistics',
        'requests_view', 'requests_view_all', 'requests_import', 'enrollees_view', 'rewarded_referents',
        'products_view', 'stock_view', 'products_transfer',
        'users_view', 'trainings_own',
    ];

    /** Contabilita': "La Coordinatrice puo' consultare tutto": the staff data of everyone, read-only. */
    public const array ACCOUNTING_SENIOR = ['staff_view_all'];
}
