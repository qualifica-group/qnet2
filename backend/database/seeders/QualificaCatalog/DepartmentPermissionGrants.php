<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The questionnaire "permessi per reparti" translated into permissions (spec
 * 0408 D-2): one grant key per question, or group of questions, of the
 * questionnaire. DepartmentRoleCatalogue composes the department roles from
 * these keys and DepartmentRoster the targeted grants of single people.
 *
 * A `<resource>.*` entry expands to every permission of that resource in the
 * real catalogue; any other name must exist there, or the seeder fails.
 */
final class DepartmentPermissionGrants
{
    /**
     * Held by every department role: own tasks, time entries and absences,
     * notes and attachments, the lookups the forms load.
     *
     * @var array<int, string>
     */
    public const array BASELINE = [
        'tasks.viewAny', 'tasks.view', 'tasks.complete', 'tasks.requestUpdate', 'tasks.viewDocuments', 'tasks.viewActivity',
        'time-entries.viewAny', 'time-entries.view', 'time-entries.create', 'time-entries.update', 'time-entries.delete', 'time-entries.export',
        'absence-requests.viewAny', 'absence-requests.create', 'absence-requests.calendarOwn',
        'notes.create', 'attachments.viewAny', 'attachments.view', 'attachments.create', 'attachments.delete',
        'operational-sites.viewAny', 'product-categories.viewAny', 'tags.viewAny',
    ];

    /** @var array<string, array<int, string>> */
    public const array GRANTS = [
        // Anagrafiche e referenti (questions 1-6).
        'registries_view' => ['registries.viewAny', 'registries.view', 'referents.viewAny', 'referents.view'],
        'registries_edit' => ['registries.create', 'registries.update', 'referents.create', 'referents.update'],
        'registries_documents' => ['registries.viewDocuments'],
        'registries_delete' => ['registries.delete', 'referents.delete'],
        'registries_transfer' => ['registries.export', 'registries.import', 'referents.export', 'referents.import'],
        'registries_history' => ['registries.viewActivity', 'referents.viewActivity'],

        // Lead e campagne (7-12), with the lookups of the lead form.
        'leads_view' => ['leads.viewAny', 'leads.view', 'business-functions.viewAny', 'sources.viewAny', 'registries.viewAny', 'referents.viewAny'],
        'leads_edit' => ['leads.create', 'leads.update'],
        'leads_import' => ['leads.import'],
        'campaigns_manage' => [
            'campaigns.viewAny', 'campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.viewActivity',
            'projects.viewAny', 'projects.view', 'projects.create', 'projects.update', 'projects.viewActivity',
            'pipeline-statuses.viewAny', 'pipeline-statuses.view',
        ],
        'leads_export' => ['leads.export', 'campaigns.export'],

        // Opportunita' (13-19).
        'opportunities_view' => ['opportunities.viewAny', 'opportunities.view'],
        'opportunities_view_competence' => ['opportunities.viewCompetence'],
        'opportunities_view_all' => ['opportunities.viewAll'],
        'opportunities_edit' => ['opportunities.create', 'opportunities.update'],
        'opportunities_delete' => ['opportunities.delete'],
        'opportunities_export' => ['opportunities.export'],
        'opportunities_import' => ['opportunities.import'],
        'opportunities_documents' => ['opportunities.viewDocuments'],
        'opportunities_history' => ['opportunities.viewActivity'],

        // Offerte (20-28): status change, PDF and commission recalculation ride on view/update.
        'quotes_view' => ['quotes.viewAny', 'quotes.view'],
        'quotes_view_competence' => ['quotes.viewCompetence'],
        'quotes_view_site' => ['quotes.viewSite'],
        'quotes_view_all' => ['quotes.viewAll'],
        // Question 23, costs and margins of the offer (spec 0412).
        'quotes_costs' => ['quotes.viewCosts'],
        'quotes_edit' => ['quotes.create', 'quotes.update'],
        'quotes_delete' => ['quotes.delete'],
        'quotes_transfer' => ['quotes.export', 'quotes.import'],
        // "Trasferisci ad altra anagrafica" on the offer (spec 0404 B-D1).
        'quotes_registry_transfer' => ['quotes.transferRegistry'],
        'quotes_history' => ['quotes.viewActivity'],

        // Commesse (29-41): chat rides on view, force close on update.
        'work_orders_view' => ['work-orders.viewAny', 'work-orders.view'],
        'work_orders_view_competence' => ['work-orders.viewCompetence'],
        'work_orders_view_site' => ['work-orders.viewSite'],
        'work_orders_view_all' => ['work-orders.viewAll'],
        'work_orders_edit' => ['work-orders.create', 'work-orders.update', 'document-bundles.viewAny', 'document-bundles.view', 'document-bundles.create', 'document-bundles.update'],
        'work_orders_economics' => ['work-orders.viewCosts', 'work-orders.viewContractData'],
        'work_orders_manage_costs' => ['work-orders.manageCosts'],
        'work_orders_invoices' => ['work-orders.viewInvoices'],
        'work_orders_payments' => ['work-orders.managePayments'],
        'work_orders_documents' => ['work-orders.viewDocuments'],
        'work_orders_emails' => ['work-orders.viewEmails', 'work-orders.sendEmail', 'email-templates.viewAny', 'email-templates.view'],
        'work_orders_delete' => ['work-orders.delete'],
        'work_orders_transfer' => ['work-orders.export', 'work-orders.import'],
        'work_orders_history' => ['work-orders.viewActivity'],

        // Contratti (42-49).
        'contracts_view' => ['contracts.viewAny', 'contracts.view'],
        'contracts_view_competence' => ['contracts.viewCompetence'],
        'contracts_view_all' => ['contracts.viewAll'],
        'contracts_validate' => ['contracts.validate'],
        'contracts_program' => ['contracts.program'],
        'contracts_change_status' => ['contracts.changeStatus'],
        'contracts_terminate' => ['contracts.terminate'],
        'contracts_reactivate' => ['contracts.reactivate'],
        'contracts_export' => ['contracts.export'],
        'contracts_history' => ['contracts.viewActivity'],

        // Attivita' (50-55).
        'tasks_view_all' => ['tasks.viewAll'],
        'tasks_view_site' => ['tasks.viewSite'],
        'tasks_edit' => ['tasks.create', 'tasks.update'],
        'tasks_manage_all' => ['tasks.manageAll'],
        'tasks_validate' => ['tasks.validate', 'tasks.block'],
        'tasks_delete' => ['tasks.delete'],
        'tasks_transfer' => ['tasks.export', 'tasks.import'],

        // Notes "notifiche creazione ed avanzamento stato Offerta, Contratto, Commessa del team" (spec 0413).
        'team_activity_notifications' => ['quotes.notifyTeam', 'contracts.notifyTeam', 'work-orders.notifyTeam'],

        // Fatturazione attiva (56-63).
        'proforma_view' => ['proforma-requests.viewAny', 'proforma-requests.view'],
        'proforma_request' => ['proforma-requests.create'],
        'invoices_view' => ['invoices.viewAny', 'invoices.view', 'invoice-installments.viewAny', 'invoice-installments.view'],
        'invoices_edit' => ['invoices.create', 'invoices.update', 'invoices.downgrade', 'invoice-installments.update'],
        'invoices_collect' => ['invoices.collect'],
        'invoices_send' => ['invoices.sendEmail', 'invoices.viewEmails'],
        'invoices_delete' => ['invoices.delete'],
        'invoices_export' => ['invoices.export', 'invoice-installments.export'],

        // Acquisti (64-71).
        'purchase_requests_view' => ['purchase-requests.view'],
        'purchase_requests_view_all' => ['purchase-requests.viewAll'],
        'purchase_requests_create' => ['purchase-requests.create', 'purchase-requests.update', 'purchase-requests.deleteLine'],
        'purchase_requests_fulfill' => ['purchase-requests.fulfill', 'purchase-requests.close'],
        'purchase_orders' => ['purchase-orders.view', 'purchase-orders.send'],
        'purchase_invoices_view' => ['purchase-invoices.viewAny', 'purchase-invoices.view', 'purchase-invoice-installments.viewAny', 'purchase-invoice-installments.view'],
        'purchase_invoices_edit' => ['purchase-invoices.create', 'purchase-invoices.update', 'purchase-invoices.downgrade', 'purchase-invoice-installments.update'],
        'purchase_invoices_pay' => ['purchase-invoices.pay'],
        'purchases_export' => ['purchase-requests.export', 'purchase-orders.export'],
        'purchase_invoices_export' => ['purchase-invoices.export', 'purchase-invoice-installments.export'],

        // Provvigioni (72-78).
        'commission_accruals_view' => ['commission-accruals.viewAny', 'commission-accruals.view'],
        'agent_fiscal_profiles_view' => ['agent-fiscal-profiles.viewAny', 'agent-fiscal-profiles.view'],
        'commission_settlements_view' => ['commission-settlements.viewAny', 'commission-settlements.view'],
        'commissions_export' => ['commission-accruals.export', 'commission-settlements.export'],
        // Not a question: the referent's "% Segnalatori" (Commissioni configurate on the
        // referent and anagrafica pages), user directive 2026-11-18 for the Back Office.
        'commission_configurations_manage' => [
            'commission-configurations.viewAny', 'commission-configurations.view', 'commission-configurations.create',
            'commission-configurations.update', 'commission-configurations.delete', 'commission-configurations.viewActivity',
        ],
        // Export without the settlements, for whoever sees only the accruals.
        'commission_accruals_export' => ['commission-accruals.export'],

        // Tesoreria e prima nota (79-84).
        'treasury_view' => ['financial-accounts.viewAny', 'financial-accounts.view', 'treasury-movements.viewAny', 'treasury-movements.view'],
        'treasury_edit' => ['treasury-movements.create', 'treasury-movements.update', 'treasury-movements.delete'],
        'treasury_reconcile' => ['treasury-movements.reconcile'],
        'ledger_view' => ['ledger.view'],
        'payment_cards_reveal' => ['financial-accounts.revealCardNumber'],
        'treasury_export' => ['treasury-movements.export'],

        // Controllo di gestione e statistiche (85-87).
        'management_control' => ['management-control.view'],
        'agent_sales_report' => ['agent-sales-report.view'],
        'request_statistics' => ['request-statistics.view'],

        // Gestione richieste (88-92). The questionnaire asks only which requests a
        // department sees (D-15): reading includes the documents, working them
        // is `requests_edit`, the "Tutte" level of a supervisor `requests_manage_all`.
        'requests_view' => ['request-management.viewAny', 'request-management.view', 'request-management.viewDocuments'],
        'requests_view_all' => ['request-management.viewAll', 'enrollee-management.viewAll'],
        'requests_import' => ['request-management.import'],
        // The mansionario's "own requests" (OperatorRoleCatalogue::OWN_REQUESTS):
        // creates and works the requests it is involved in, with the work panel's
        // lookups and the request to change a protected source.
        'requests_edit' => [
            'request-management.create', 'request-management.update', 'request-management.export',
            'request-management.viewActivity', 'request-management.appendTeamMember', 'request-management.assignManagerGa1',
            'request-management.transferContact', 'request-management.transferRegistry',
            'registries.viewAny', 'referents.viewAny', 'referents.create', 'sources.viewAny', 'users.viewAny',
            'field-change-requests.create',
        ],
        // The mansionario's "all requests" and "all enrollees": deletes, assigns
        // the operator, writes the source, and the whole Gestione Iscritti.
        'requests_manage_all' => [
            'request-management.delete', 'request-management.assignOperator', 'request-management.updateSource',
            'business-functions.viewAny', 'enrollee-management.*',
        ],
        // Whoever is notified of the source change requests reviews them.
        'field_change_reviews' => [
            'field-change-requests.viewAny', 'field-change-requests.view', 'field-change-requests.manage',
            'field-change-requests.viewActivity', 'request-management.updateSource', 'enrollee-management.updateSource',
        ],
        'enrollees_view' => ['enrollee-management.viewAny', 'enrollee-management.view', 'enrollee-management.viewDocuments'],
        'rewarded_referents' => ['rewarded-referents.viewAny', 'rewarded-referents.view', 'rewarded-referents.create', 'rewarded-referents.update', 'rewarded-referents.viewActivity'],

        // Prodotti e magazzino (93-97).
        'products_view' => ['products.viewAny', 'products.view'],
        'products_edit' => ['products.create', 'products.update'],
        'products_delete' => ['products.delete'],
        'products_transfer' => ['products.export', 'products.import'],
        'stock_view' => ['stock.viewAny'],

        // Personale (98-107).
        'trainings_own' => ['staff-trainings.viewAny', 'user-skills.viewAny'],
        'users_view' => ['users.viewAny', 'users.view', 'org-chart.view'],
        'absences_calendar_all' => ['absence-requests.calendarAll'],
        'staff_view_all' => ['staff-trainings.viewAll', 'user-skills.viewAll', 'absence-requests.calendarAll', 'time-entries.viewAll'],
        'hr_office' => [
            'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete', 'users.export', 'users.import',
            'users.viewActivity', 'users.manageCompensation',
            'job-roles.*', 'job-activities.*', 'business-functions.*', 'org-chart.*', 'absence-requests.*', 'absence-types.*',
            'company-closures.*', 'contract-types.*', 'staff-courses.*', 'staff-trainings.*', 'skills.*', 'user-skills.*',
            'time-entries.viewAll', 'time-entries.manageAll', 'time-entries.exportMonthly', 'companies.viewAny', 'companies.view',
        ],
    ];
}
