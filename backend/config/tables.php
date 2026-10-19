<?php

use App\Tables\AbsenceRequests\AbsenceRequestsAllTableDefinition;
use App\Tables\AbsenceRequests\AbsenceRequestsTableDefinition;
use App\Tables\AbsenceRequests\AbsenceRequestsToApproveTableDefinition;
use App\Tables\AbsenceTypesTableDefinition;
use App\Tables\AgentFiscalProfilesTableDefinition;
use App\Tables\ApiClientsTableDefinition;
use App\Tables\AttributesTableDefinition;
use App\Tables\BusinessFunctionsTableDefinition;
use App\Tables\CampaignsTableDefinition;
use App\Tables\CollectiveAgreementsTableDefinition;
use App\Tables\CommissionAccrualsTableDefinition;
use App\Tables\CommissionConfigurationsTableDefinition;
use App\Tables\CommissionSettlementRunsTableDefinition;
use App\Tables\CommissionSettlementsTableDefinition;
use App\Tables\CompaniesTableDefinition;
use App\Tables\CompanySitesTableDefinition;
use App\Tables\ContractsTableDefinition;
use App\Tables\ContractStatusesTableDefinition;
use App\Tables\CostTypesTableDefinition;
use App\Tables\CustomFieldsTableDefinition;
use App\Tables\DocumentBundlesTableDefinition;
use App\Tables\DocumentLayoutsTableDefinition;
use App\Tables\EmailTemplatesTableDefinition;
use App\Tables\EnasarcoParametersTableDefinition;
use App\Tables\EnrolleeManagementTableDefinition;
use App\Tables\FieldChangeRequestsTableDefinition;
use App\Tables\FinancialAccountsTableDefinition;
use App\Tables\InvoiceInstallmentsTableDefinition;
use App\Tables\InvoicesTableDefinition;
use App\Tables\InvoiceTagsTableDefinition;
use App\Tables\JobActivitiesTableDefinition;
use App\Tables\JobRolesTableDefinition;
use App\Tables\LeadImportsTableDefinition;
use App\Tables\LeadsTableDefinition;
use App\Tables\NotificationsTableDefinition;
use App\Tables\OperationalSitesTableDefinition;
use App\Tables\OpportunitiesTableDefinition;
use App\Tables\PaymentMethodsTableDefinition;
use App\Tables\PipelineStatusesTableDefinition;
use App\Tables\ProductCategoriesTableDefinition;
use App\Tables\ProductsTableDefinition;
use App\Tables\ProductTypologiesTableDefinition;
use App\Tables\ProformaRequestsTableDefinition;
use App\Tables\ProjectsTableDefinition;
use App\Tables\PurchaseInvoiceInstallmentsTableDefinition;
use App\Tables\PurchaseInvoicesTableDefinition;
use App\Tables\PurchaseOrdersTableDefinition;
use App\Tables\PurchaseOrderStatusesTableDefinition;
use App\Tables\PurchaseRequestLinesTableDefinition;
use App\Tables\PurchaseRequestLineStatusesTableDefinition;
use App\Tables\PurchaseRequestPrioritiesTableDefinition;
use App\Tables\PurchaseRequestsTableDefinition;
use App\Tables\QuotesTableDefinition;
use App\Tables\QuoteWorkflowsTableDefinition;
use App\Tables\ReferentsTableDefinition;
use App\Tables\ReferentTypesTableDefinition;
use App\Tables\RegistriesTableDefinition;
use App\Tables\RequestManagementTableDefinition;
use App\Tables\RewardedReferentsTableDefinition;
use App\Tables\RewardStatusesTableDefinition;
use App\Tables\RewardTypesTableDefinition;
use App\Tables\RolesTableDefinition;
use App\Tables\SectorsTableDefinition;
use App\Tables\SkillsTableDefinition;
use App\Tables\SourcesTableDefinition;
use App\Tables\StaffCoursesTableDefinition;
use App\Tables\StaffTrainings\StaffTrainingsTableDefinition;
use App\Tables\TagsTableDefinition;
use App\Tables\TaskCategoriesTableDefinition;
use App\Tables\TaskImportancesTableDefinition;
use App\Tables\TaskPrioritiesTableDefinition;
use App\Tables\TasksTableDefinition;
use App\Tables\TaskStatusesTableDefinition;
use App\Tables\TaskTemplatesTableDefinition;
use App\Tables\TaskTypesTableDefinition;
use App\Tables\UnitsOfMeasureTableDefinition;
use App\Tables\UserSkills\UserSkillsTableDefinition;
use App\Tables\UsersTableDefinition;
use App\Tables\VatRatesTableDefinition;
use App\Tables\WorkOrderPaymentStatusesTableDefinition;
use App\Tables\WorkOrdersTableDefinition;

return [

    /*
    |--------------------------------------------------------------------------
    | Generic Domain-driven Table Registry
    |--------------------------------------------------------------------------
    |
    | Maps each table `{domain}` to its TableDefinition class. One pair of
    | endpoints (GET /api/tables/{domain}/columns, POST /api/tables/{domain}/rows)
    | serves every domain; TableRegistry resolves the class below through the
    | container (so its dependencies are injected). An unregistered {domain}
    | resolves to nothing and yields a 404.
    |
    | Adding a domain (e.g. products):
    |   1. class ProductsTableDefinition extends AbstractTableDefinition
    |   2. add 'products' => ProductsTableDefinition::class here
    | No new controller, service, request, resource or route is needed.
    |
    | See docs/adr/0002-generic-domain-driven-table-registry.md
    |     docs/api/0002-generic-tables.md
    */

    'definitions' => [
        'users' => UsersTableDefinition::class,
        'api-clients' => ApiClientsTableDefinition::class,
        'roles' => RolesTableDefinition::class,
        'business-functions' => BusinessFunctionsTableDefinition::class,
        // spec 0271: HR org structure catalogues.
        'job-roles' => JobRolesTableDefinition::class,
        'job-activities' => JobActivitiesTableDefinition::class,
        // spec 0278: absence types catalogue.
        'absence-types' => AbsenceTypesTableDefinition::class,
        // spec 0278: absence requests, one domain per tab (mine / to approve / all).
        'absence-requests' => AbsenceRequestsTableDefinition::class,
        'absence-requests-to-approve' => AbsenceRequestsToApproveTableDefinition::class,
        'absence-requests-all' => AbsenceRequestsAllTableDefinition::class,
        // spec 0284: staff courses catalogue and staff trainings register.
        'staff-courses' => StaffCoursesTableDefinition::class,
        'staff-trainings' => StaffTrainingsTableDefinition::class,
        // spec 0286: skills catalogue and user skills register.
        'skills' => SkillsTableDefinition::class,
        'user-skills' => UserSkillsTableDefinition::class,
        // spec 0272: collective agreements (CCNL) with their levels.
        'collective-agreements' => CollectiveAgreementsTableDefinition::class,
        'companies' => CompaniesTableDefinition::class,
        'commission-configurations' => CommissionConfigurationsTableDefinition::class,
        'company-sites' => CompanySitesTableDefinition::class,
        // spec 0072: il modulo Contratti e il suo configuratore di stati.
        'contracts' => ContractsTableDefinition::class,
        'contract-statuses' => ContractStatusesTableDefinition::class,
        'operational-sites' => OperationalSitesTableDefinition::class,
        'referent-types' => ReferentTypesTableDefinition::class,
        'referents' => ReferentsTableDefinition::class,
        'registries' => RegistriesTableDefinition::class,
        'sectors' => SectorsTableDefinition::class,
        'attributes' => AttributesTableDefinition::class,
        'custom-fields' => CustomFieldsTableDefinition::class,
        'document-layouts' => DocumentLayoutsTableDefinition::class,
        // spec 0078: the dedicated browse view over every proposed field
        // change (any resource/field/status).
        'field-change-requests' => FieldChangeRequestsTableDefinition::class,
        'product-categories' => ProductCategoriesTableDefinition::class,
        'products' => ProductsTableDefinition::class,
        'sources' => SourcesTableDefinition::class,
        'tags' => TagsTableDefinition::class,
        'pipeline-statuses' => PipelineStatusesTableDefinition::class,
        'projects' => ProjectsTableDefinition::class,
        'campaigns' => CampaignsTableDefinition::class,
        'leads' => LeadsTableDefinition::class,
        'import-runs' => LeadImportsTableDefinition::class,
        'opportunities' => OpportunitiesTableDefinition::class,
        'payment-methods' => PaymentMethodsTableDefinition::class,
        'quotes' => QuotesTableDefinition::class,
        'quote-workflows' => QuoteWorkflowsTableDefinition::class,
        // spec 0093: le Commesse, collegate a un'offerta e alle sue righe.
        'work-orders' => WorkOrdersTableDefinition::class,
        // spec 0201: the configurable payment statuses of a commessa line.
        'work-order-payment-statuses' => WorkOrderPaymentStatusesTableDefinition::class,
        'request-management' => RequestManagementTableDefinition::class,
        // spec 0130: "Gestione Iscritti", the same grid restricted to
        // validated/closed_won rows and governed by its own permission set.
        'enrollee-management' => EnrolleeManagementTableDefinition::class,
        'reward-types' => RewardTypesTableDefinition::class,
        'reward-statuses' => RewardStatusesTableDefinition::class,
        'rewarded-referents' => RewardedReferentsTableDefinition::class,
        'vat-rates' => VatRatesTableDefinition::class,
        'units-of-measure' => UnitsOfMeasureTableDefinition::class,
        // spec 0210: tipi di costo (righe ODA).
        'cost-types' => CostTypesTableDefinition::class,
        // spec 0247: catalogs of the simple purchasing/accounting lists.
        'purchase-request-priorities' => PurchaseRequestPrioritiesTableDefinition::class,
        // spec 0248: presentation catalogues of the ODA and RDA line statuses.
        'purchase-order-statuses' => PurchaseOrderStatusesTableDefinition::class,
        'purchase-request-line-statuses' => PurchaseRequestLineStatusesTableDefinition::class,
        'invoice-tags' => InvoiceTagsTableDefinition::class,
        'financial-accounts' => FinancialAccountsTableDefinition::class,
        // spec 0212: profili fiscali degli agenti.
        'agent-fiscal-profiles' => AgentFiscalProfilesTableDefinition::class,
        // spec 0212: maturato provvigioni (libro mastro in sola lettura).
        'commission-accruals' => CommissionAccrualsTableDefinition::class,
        // spec 0213: liquidazioni agenti (lanci, estratti) e parametri ENASARCO.
        'commission-settlement-runs' => CommissionSettlementRunsTableDefinition::class,
        'commission-settlements' => CommissionSettlementsTableDefinition::class,
        'enasarco-parameters' => EnasarcoParametersTableDefinition::class,
        'proforma-requests' => ProformaRequestsTableDefinition::class,
        // spec 0208: purchase requests (RDA) and their line management grid.
        'purchase-requests' => PurchaseRequestsTableDefinition::class,
        'purchase-request-lines' => PurchaseRequestLinesTableDefinition::class,
        // spec 0210: purchase orders (ODA).
        'purchase-orders' => PurchaseOrdersTableDefinition::class,
        // spec 0211: scadenziario passivo (rate dei documenti passivi), raggruppabile.
        'purchase-invoice-installments' => PurchaseInvoiceInstallmentsTableDefinition::class,
        // spec 0250: payable documents, one row per document.
        'purchase-invoices' => PurchaseInvoicesTableDefinition::class,
        // spec 0194: active invoicing (proforma and invoices).
        'invoices' => InvoicesTableDefinition::class,
        // spec 0197: installments of the active invoices, groupable.
        'invoice-installments' => InvoiceInstallmentsTableDefinition::class,
        'product-typologies' => ProductTypologiesTableDefinition::class,
        // spec 0101: il modulo Task e i suoi cinque configuratori.
        'tasks' => TasksTableDefinition::class,
        'task-statuses' => TaskStatusesTableDefinition::class,
        'task-types' => TaskTypesTableDefinition::class,
        'task-categories' => TaskCategoriesTableDefinition::class,
        'task-priorities' => TaskPrioritiesTableDefinition::class,
        'task-importances' => TaskImportancesTableDefinition::class,
        // spec 0124: il modulo Modelli di Task.
        'task-templates' => TaskTemplatesTableDefinition::class,
        // spec 0150: la pagina "Notifiche", una riga per ciascuna delle
        // notifiche dell'attore (scoped in baseQuery, mai globale).
        'notifications' => NotificationsTableDefinition::class,
        // spec 0175: "Modelli email" e "Modelli documenti", le tabelle di
        // appoggio del modulo email dalla Commessa.
        'email-templates' => EmailTemplatesTableDefinition::class,
        'document-bundles' => DocumentBundlesTableDefinition::class,
    ],

];
