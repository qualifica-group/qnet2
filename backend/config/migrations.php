<?php

use App\Migrations\Sources\AttributeLayoutsSource;
use App\Migrations\Sources\AttributesSource;
use App\Migrations\Sources\BusinessFunctionMembersSource;
use App\Migrations\Sources\BusinessFunctionsSource;
use App\Migrations\Sources\CollectionsSource;
use App\Migrations\Sources\CommissionConfigurationsSource;
use App\Migrations\Sources\CompaniesSource;
use App\Migrations\Sources\CompanySitesSource;
use App\Migrations\Sources\CostProductsSource;
use App\Migrations\Sources\DocumentBundlesSource;
use App\Migrations\Sources\EmailTemplatesSource;
use App\Migrations\Sources\FinancialAccountsSource;
use App\Migrations\Sources\InvoicesSource;
use App\Migrations\Sources\NotesSource;
use App\Migrations\Sources\OperationalSitesSource;
use App\Migrations\Sources\OpportunitiesSource;
use App\Migrations\Sources\PaymentMethodsSource;
use App\Migrations\Sources\ProductCategoriesSource;
use App\Migrations\Sources\ProductCategoryAttributesSource;
use App\Migrations\Sources\ProductsSource;
use App\Migrations\Sources\ProductSuppliersSource;
use App\Migrations\Sources\ProformaRequestsSource;
use App\Migrations\Sources\PurchaseInvoicesSource;
use App\Migrations\Sources\PurchaseOrdersSource;
use App\Migrations\Sources\PurchaseRequestsSource;
use App\Migrations\Sources\QuotesSource;
use App\Migrations\Sources\ReferentsSource;
use App\Migrations\Sources\ReferentTypesSource;
use App\Migrations\Sources\RegistriesSource;
use App\Migrations\Sources\RolesSource;
use App\Migrations\Sources\SectorsSource;
use App\Migrations\Sources\SourcesSource;
use App\Migrations\Sources\TagsSource;
use App\Migrations\Sources\TasksSource;
use App\Migrations\Sources\TaskTemplatesSource;
use App\Migrations\Sources\UsersSource;
use App\Migrations\Sources\VatRatesSource;
use App\Migrations\Sources\WorkOrderProgressSource;
use App\Migrations\Sources\WorkOrdersSource;
use App\Migrations\Sources\WorkOrderWorkflowsSource;
use App\Migrations\Sources\WorkOrderWorkflowStatusesSource;

return [

    /*
    |--------------------------------------------------------------------------
    | External system connection (spec 0013)
    |--------------------------------------------------------------------------
    |
    | Base URL + Bearer token of the external system's read-only API. Never
    | hard-coded: both come from env only (.env.example ships with no value).
    | The token is never returned/logged (see App\Migrations\Support\
    | ExternalApiClient).
    */

    'base_url' => env('EXTERNAL_MIGRATION_BASE_URL'),
    'token' => env('EXTERNAL_MIGRATION_TOKEN'),
    'timeout' => (int) env('EXTERNAL_MIGRATION_TIMEOUT', 15),

    // Bounded retry for a transient connection failure before it is normalized
    // into ExternalApiException (App\Migrations\Support\ExternalApiClient).
    'retry_times' => 2,
    'retry_sleep_ms' => 200,

    // MIGRATIONS_DEFAULT_PER_PAGE / MIGRATIONS_MAX_PER_PAGE: bounds for the
    // preview endpoint's `per_page` query param (App\Http\Requests\Migration\
    // MigrationPreviewRequest).
    'default_per_page' => 50,
    'max_per_page' => 200,

    // Page size RunMigrationJob requests from the external system while
    // paging through an entire source during the import phase (independent
    // of the user-controlled preview per_page).
    'import_batch_size' => 100,

    // RunMigrationJob/RunMassMigrationJob `$timeout`: what the worker enforces
    // per job, over `queue:work --timeout` (whose 60s default killed every real
    // migration, leaving its run `processing`). Must stay BELOW
    // queue.connections.database.retry_after, otherwise a second worker
    // reclaims a migration still legitimately running on the first.
    'job_timeout' => (int) env('MIGRATION_JOB_TIMEOUT', 1800),

    // Spec 0285: the `users` source only links the legacy users to the qnet
    // accounts that already exist (writes `old_id`, creates nothing), for an
    // installation whose accounts come from its own seeder.
    'users_link_only' => (bool) env('EXTERNAL_MIGRATION_USERS_LINK_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Generic, registry-driven migration source map
    |--------------------------------------------------------------------------
    |
    | Maps each `{source}` key to its MigrationSource class, resolved through
    | the container (mirrors config/tables.php / config/imports.php). Adding a
    | resource = one class + one line here. An unregistered {source} 404s via
    | App\Migrations\MigrationRegistry.
    */

    'definitions' => [
        'roles' => RolesSource::class,
        'users' => UsersSource::class,
        'business-functions' => BusinessFunctionsSource::class,
        'companies' => CompaniesSource::class,
        'company-sites' => CompanySitesSource::class,
        'operational-sites' => OperationalSitesSource::class,
        'business-function-members' => BusinessFunctionMembersSource::class,
        'referent-types' => ReferentTypesSource::class,
        'referents' => ReferentsSource::class,
        'sources' => SourcesSource::class,
        'tags' => TagsSource::class,
        'sectors' => SectorsSource::class,
        'task-templates' => TaskTemplatesSource::class,
        'vat-rates' => VatRatesSource::class,
        'payment-methods' => PaymentMethodsSource::class,
        'attributes' => AttributesSource::class,
        'product-categories' => ProductCategoriesSource::class,
        'product-category-attributes' => ProductCategoryAttributesSource::class,
        'attribute-layouts' => AttributeLayoutsSource::class,
        'products' => ProductsSource::class,
        'cost-products' => CostProductsSource::class,
        // spec 0175: "Modelli email" / "Modelli documenti", independent
        // phase-1 anchors (D-13).
        'email-templates' => EmailTemplatesSource::class,
        'document-bundles' => DocumentBundlesSource::class,
        // spec 0189: the legacy operational records, a strict parent chain
        // (registry <- opportunity <- quote <- work order).
        'registries' => RegistriesSource::class,
        'opportunities' => OpportunitiesSource::class,
        'quotes' => QuotesSource::class,
        'work-orders' => WorkOrdersSource::class,
        // spec 0289: the legacy tasks, needing users and the migrated work orders.
        'tasks' => TasksSource::class,
        // spec 0321: the manual progress entries of the migrated work orders.
        'work-order-progress' => WorkOrderProgressSource::class,
        // spec 0322: the work order workflows and the current status of each work order.
        'work-order-workflows' => WorkOrderWorkflowsSource::class,
        'work-order-workflow-statuses' => WorkOrderWorkflowStatusesSource::class,
        // spec 0203: the product supplier link and the legacy commission rules,
        // both needing the migrated registries.
        'product-suppliers' => ProductSuppliersSource::class,
        'commission-configurations' => CommissionConfigurationsSource::class,
        // spec 0294: the legacy RDA, first source of the accounting part.
        'purchase-requests' => PurchaseRequestsSource::class,
        // spec 0296: the legacy ODA, after the RDA their lines link to.
        'purchase-orders' => PurchaseOrdersSource::class,
        // spec 0297: the legacy bank accounts and cash boxes.
        'financial-accounts' => FinancialAccountsSource::class,
        // spec 0298: the legacy supplier invoices, billing the migrated ODA.
        'purchase-invoices' => PurchaseInvoicesSource::class,
        // spec 0300: the legacy proforma requests, before the active invoices.
        'proforma-requests' => ProformaRequestsSource::class,
        // spec 0301: the legacy customer and institution proformas.
        'invoices' => InvoicesSource::class,
        // spec 0302: every legacy collection, recorded where still missing.
        'collections' => CollectionsSource::class,
        // spec 0311: the notes of every legacy module, after the records they belong to.
        'notes' => NotesSource::class,
    ],

];
