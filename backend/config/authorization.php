<?php

use App\Authorization\AbsenceRequestsAuthorization;
use App\Authorization\AbsenceTypesAuthorization;
use App\Authorization\AgentFiscalProfilesAuthorization;
use App\Authorization\ApiClientsAuthorization;
use App\Authorization\AttributesAuthorization;
use App\Authorization\BusinessFunctionsAuthorization;
use App\Authorization\CampaignsAuthorization;
use App\Authorization\CollectiveAgreementsAuthorization;
use App\Authorization\CommissionAccrualsAuthorization;
use App\Authorization\CommissionConfigurationsAuthorization;
use App\Authorization\CommissionSettlementsAuthorization;
use App\Authorization\CompaniesAuthorization;
use App\Authorization\CompanySitesAuthorization;
use App\Authorization\ContractsAuthorization;
use App\Authorization\ContractStatusesAuthorization;
use App\Authorization\ContractTypesAuthorization;
use App\Authorization\CostTypesAuthorization;
use App\Authorization\CustomFieldsAuthorization;
use App\Authorization\DocumentBundlesAuthorization;
use App\Authorization\DocumentLayoutsAuthorization;
use App\Authorization\EmailTemplatesAuthorization;
use App\Authorization\EnasarcoParametersAuthorization;
use App\Authorization\EnrolleeManagementAuthorization;
use App\Authorization\FinancialAccountsAuthorization;
use App\Authorization\InvoiceInstallmentsAuthorization;
use App\Authorization\InvoicesAuthorization;
use App\Authorization\InvoiceTagsAuthorization;
use App\Authorization\JobActivitiesAuthorization;
use App\Authorization\JobRolesAuthorization;
use App\Authorization\LeadsAuthorization;
use App\Authorization\LedgerAuthorization;
use App\Authorization\ManagementControlAuthorization;
use App\Authorization\OperationalSitesAuthorization;
use App\Authorization\OpportunitiesAuthorization;
use App\Authorization\OrgChartAuthorization;
use App\Authorization\PaymentMethodsAuthorization;
use App\Authorization\PipelineStatusesAuthorization;
use App\Authorization\ProductCategoriesAuthorization;
use App\Authorization\ProductsAuthorization;
use App\Authorization\ProductTypologiesAuthorization;
use App\Authorization\ProformaRequestsAuthorization;
use App\Authorization\ProjectsAuthorization;
use App\Authorization\PurchaseInvoiceInstallmentsAuthorization;
use App\Authorization\PurchaseInvoicesAuthorization;
use App\Authorization\PurchaseOrdersAuthorization;
use App\Authorization\PurchaseOrderStatusesAuthorization;
use App\Authorization\PurchaseRequestLineStatusesAuthorization;
use App\Authorization\PurchaseRequestPrioritiesAuthorization;
use App\Authorization\PurchaseRequestsAuthorization;
use App\Authorization\QuotesAuthorization;
use App\Authorization\QuoteWorkflowsAuthorization;
use App\Authorization\ReferentsAuthorization;
use App\Authorization\ReferentTypesAuthorization;
use App\Authorization\RegistriesAuthorization;
use App\Authorization\RequestManagementAuthorization;
use App\Authorization\RequestStatisticsAuthorization;
use App\Authorization\RewardedReferentsAuthorization;
use App\Authorization\RewardStatusesAuthorization;
use App\Authorization\RewardTypesAuthorization;
use App\Authorization\RolesAuthorization;
use App\Authorization\SectorsAuthorization;
use App\Authorization\SkillsAuthorization;
use App\Authorization\SourcesAuthorization;
use App\Authorization\StaffCoursesAuthorization;
use App\Authorization\StaffTrainingsAuthorization;
use App\Authorization\TagsAuthorization;
use App\Authorization\TaskCategoriesAuthorization;
use App\Authorization\TaskImportancesAuthorization;
use App\Authorization\TaskPrioritiesAuthorization;
use App\Authorization\TasksAuthorization;
use App\Authorization\TaskStatusesAuthorization;
use App\Authorization\TaskTemplatesAuthorization;
use App\Authorization\TaskTypesAuthorization;
use App\Authorization\UnitsOfMeasureAuthorization;
use App\Authorization\UsersAuthorization;
use App\Authorization\UserSkillsAuthorization;
use App\Authorization\VatRatesAuthorization;
use App\Authorization\WorkOrderPaymentStatusesAuthorization;
use App\Authorization\WorkOrdersAuthorization;

return [

    /*
    |--------------------------------------------------------------------------
    | Centralized Authorization Metadata Registry
    |--------------------------------------------------------------------------
    |
    | Maps each resource key to its ResourceAuthorization class. Resolved
    | through the container by App\Authorization\AuthorizationRegistry (so its
    | dependencies are injected), the same pattern as config/tables.php.
    |
    | GET /api/meta/{resource} and the users/roles CRUD endpoints all read
    | from this map. Adding a resource:
    |   1. class ProductsAuthorization extends AbstractResourceAuthorization
    |   2. add 'products' => ProductsAuthorization::class here
    | No new controller or route is needed.
    |
    | See docs/specs/0004-centralized-authorization-metadata.md
    |     docs/conventions/metadata-driven-forms.md
    */

    'definitions' => [
        'users' => UsersAuthorization::class,
        'roles' => RolesAuthorization::class,
        'business-functions' => BusinessFunctionsAuthorization::class,
        // spec 0271: HR org structure catalogues.
        'job-roles' => JobRolesAuthorization::class,
        'job-activities' => JobActivitiesAuthorization::class,
        // spec 0272: collective agreements (CCNL).
        'collective-agreements' => CollectiveAgreementsAuthorization::class,
        'companies' => CompaniesAuthorization::class,
        'commission-configurations' => CommissionConfigurationsAuthorization::class,
        'company-sites' => CompanySitesAuthorization::class,
        // spec 0072: il modulo Contratti e il suo configuratore di stati.
        'contracts' => ContractsAuthorization::class,
        'contract-statuses' => ContractStatusesAuthorization::class,
        'operational-sites' => OperationalSitesAuthorization::class,
        'referent-types' => ReferentTypesAuthorization::class,
        'referents' => ReferentsAuthorization::class,
        'registries' => RegistriesAuthorization::class,
        'sectors' => SectorsAuthorization::class,
        'attributes' => AttributesAuthorization::class,
        'custom-fields' => CustomFieldsAuthorization::class,
        'document-layouts' => DocumentLayoutsAuthorization::class,
        'product-categories' => ProductCategoriesAuthorization::class,
        'products' => ProductsAuthorization::class,
        'sources' => SourcesAuthorization::class,
        'tags' => TagsAuthorization::class,
        'pipeline-statuses' => PipelineStatusesAuthorization::class,
        'projects' => ProjectsAuthorization::class,
        'campaigns' => CampaignsAuthorization::class,
        'leads' => LeadsAuthorization::class,
        'opportunities' => OpportunitiesAuthorization::class,
        'payment-methods' => PaymentMethodsAuthorization::class,
        'quotes' => QuotesAuthorization::class,
        'quote-workflows' => QuoteWorkflowsAuthorization::class,
        // spec 0093: le Commesse, collegate a un'offerta e alle sue righe.
        'work-orders' => WorkOrdersAuthorization::class,
        'work-order-payment-statuses' => WorkOrderPaymentStatusesAuthorization::class,
        // spec 0175: le email della Commessa e i loro due configuratori.
        'email-templates' => EmailTemplatesAuthorization::class,
        'document-bundles' => DocumentBundlesAuthorization::class,
        'request-management' => RequestManagementAuthorization::class,
        // Spec 0130: "Gestione Iscritti" — same field/action catalogue as
        // request-management, own resource() (see the class docblock).
        'enrollee-management' => EnrolleeManagementAuthorization::class,
        // Spec 0185: "Statistiche Gestione Richieste" — a view-only module.
        'request-statistics' => RequestStatisticsAuthorization::class,
        // Spec 0273: the HR "Organigramma" page (view-only permission).
        'org-chart' => OrgChartAuthorization::class,
        // spec 0278: HR absence types catalogue and absence requests.
        'absence-types' => AbsenceTypesAuthorization::class,
        // spec 0290: contract types catalogue.
        'contract-types' => ContractTypesAuthorization::class,
        'absence-requests' => AbsenceRequestsAuthorization::class,
        // spec 0284: HR staff courses catalogue and staff trainings register.
        'staff-courses' => StaffCoursesAuthorization::class,
        'staff-trainings' => StaffTrainingsAuthorization::class,
        // spec 0286: HR skills catalogue and user skills register.
        'skills' => SkillsAuthorization::class,
        'user-skills' => UserSkillsAuthorization::class,
        'api-clients' => ApiClientsAuthorization::class,
        'reward-types' => RewardTypesAuthorization::class,
        'reward-statuses' => RewardStatusesAuthorization::class,
        'rewarded-referents' => RewardedReferentsAuthorization::class,
        'vat-rates' => VatRatesAuthorization::class,
        'units-of-measure' => UnitsOfMeasureAuthorization::class,
        // spec 0210: tipi di costo (righe ODA).
        'cost-types' => CostTypesAuthorization::class,
        // spec 0247: catalogs of the simple purchasing/accounting lists.
        'purchase-request-priorities' => PurchaseRequestPrioritiesAuthorization::class,
        // spec 0248: presentation catalogues of the ODA and RDA line statuses.
        'purchase-order-statuses' => PurchaseOrderStatusesAuthorization::class,
        'purchase-request-line-statuses' => PurchaseRequestLineStatusesAuthorization::class,
        'invoice-tags' => InvoiceTagsAuthorization::class,
        // spec 0189: conti finanziari (conto corrente, carta, cassa).
        'financial-accounts' => FinancialAccountsAuthorization::class,
        // spec 0212: profili fiscali degli agenti.
        'agent-fiscal-profiles' => AgentFiscalProfilesAuthorization::class,
        // spec 0212: maturato provvigioni (libro mastro in sola lettura).
        'commission-accruals' => CommissionAccrualsAuthorization::class,
        // spec 0213: estratti di liquidazione agenti (importi in sola lettura, note in bozza).
        'commission-settlements' => CommissionSettlementsAuthorization::class,
        // spec 0213: parametri ENASARCO per anno.
        'enasarco-parameters' => EnasarcoParametersAuthorization::class,
        // spec 0193: richieste proforma alla Contabilita'.
        'proforma-requests' => ProformaRequestsAuthorization::class,
        // spec 0208: richieste di acquisto (RDA).
        'purchase-requests' => PurchaseRequestsAuthorization::class,
        // spec 0210: ordini di acquisto (ODA).
        'purchase-orders' => PurchaseOrdersAuthorization::class,
        // spec 0250: le fatture passive (proforma e fatture dei fornitori).
        'purchase-invoices' => PurchaseInvoicesAuthorization::class,
        // spec 0211: le scadenze passive (rate dei documenti passivi).
        'purchase-invoice-installments' => PurchaseInvoiceInstallmentsAuthorization::class,
        // spec 0194: fatturazione attiva (proforma e fatture).
        'invoices' => InvoicesAuthorization::class,
        // spec 0197: le scadenze (rate) delle fatture attive.
        'invoice-installments' => InvoiceInstallmentsAuthorization::class,
        // spec 0266: Partitario clienti/fornitori (sola lettura, solo `ledger.view`).
        'ledger' => LedgerAuthorization::class,
        // spec 0268: Controllo di gestione (sola lettura, solo `management-control.view`).
        'management-control' => ManagementControlAuthorization::class,
        'product-typologies' => ProductTypologiesAuthorization::class,
        // spec 0101: il modulo Task e i suoi cinque configuratori.
        'tasks' => TasksAuthorization::class,
        'task-statuses' => TaskStatusesAuthorization::class,
        'task-types' => TaskTypesAuthorization::class,
        'task-categories' => TaskCategoriesAuthorization::class,
        'task-priorities' => TaskPrioritiesAuthorization::class,
        'task-importances' => TaskImportancesAuthorization::class,
        // spec 0124: il modulo Modelli di Task.
        'task-templates' => TaskTemplatesAuthorization::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission-only resources
    |--------------------------------------------------------------------------
    |
    | Resources that own a real permission but NO form of their own, so they
    | have no ResourceAuthorization above (no fields, no metadata endpoint) —
    | yet their permission must stay assignable from the Role form.
    |
    | `notes` (spec 0052 D-6) and `attachments` are the two cases this exists
    | for: both are agnostic cross-module components mounted by other modules,
    | so neither owns a form of its own, yet both gate their endpoints on real
    | permissions (`notes.create`; `attachments.viewAny/view/create/delete`).
    | Without this list those permissions exist in the catalogue while being
    | un-grantable from the UI — reachable only by a seeder or the super-admin
    | bypass. Note that the host module's own gate (e.g.
    | `request-management.viewDocuments`) only opens the surface: it does not
    | authorize the component's endpoints.
    |
    | This is NOT the place for the indirect sub-entity permissions
    | (addresses.*, contacts.*, personal_data.*): those are governed by the
    | field-permission matrix of their parent form and must stay out of the
    | Role form checkboxes.
    |
    | See App\Authorization\AssignablePermissionCatalogue
    */

    'permission_only_resources' => [
        'notes',
        'attachments',
        // Spec 0158: `table-filter-views.publish` gates `visibility: shared`
        // on a saved filter view — a real permission with no form of its own.
        'table-filter-views',
        // `legacy-ids.view` (LegacyIdPolicy): see the legacy `old_id` on grids
        // and record details — cross-cutting, no form of its own.
        'legacy-ids',
    ],

];
