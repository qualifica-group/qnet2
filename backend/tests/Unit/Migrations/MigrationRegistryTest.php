<?php

use App\Migrations\MigrationRegistry;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-005 — registry resolve + unknown source
// ---------------------------------------------------------------------------

it('resolves a registered source to its class via the container (dependencies injected)', function () {
    $source = app(MigrationRegistry::class)->resolve('roles');

    expect($source)->toBeInstanceOf(RolesSource::class)
        ->and($source->key())->toBe('roles');
});

it('throws ModelNotFoundException for an unregistered source (404 via BaseApiController)', function () {
    expect(fn () => app(MigrationRegistry::class)->resolve('unknown-source'))
        ->toThrow(ModelNotFoundException::class);
});

it('config/migrations.php registers every source (spec 0013 Increment 2)', function () {
    expect(config('migrations.definitions'))->toBe([
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
        'email-templates' => EmailTemplatesSource::class,
        'document-bundles' => DocumentBundlesSource::class,
        'registries' => RegistriesSource::class,
        'opportunities' => OpportunitiesSource::class,
        'quotes' => QuotesSource::class,
        'work-orders' => WorkOrdersSource::class,
        'tasks' => TasksSource::class,
        // REQUIREMENT CHANGED (spec 0321): the work order progress source.
        'work-order-progress' => WorkOrderProgressSource::class,
        'product-suppliers' => ProductSuppliersSource::class,
        'commission-configurations' => CommissionConfigurationsSource::class,
        'purchase-requests' => PurchaseRequestsSource::class,
        'purchase-orders' => PurchaseOrdersSource::class,
        'financial-accounts' => FinancialAccountsSource::class,
        'purchase-invoices' => PurchaseInvoicesSource::class,
        'proforma-requests' => ProformaRequestsSource::class,
        'invoices' => InvoicesSource::class,
        'collections' => CollectionsSource::class,
        // REQUIREMENT CHANGED (spec 0311): the legacy notes source.
        'notes' => NotesSource::class,
    ]);
});

it('all() resolves every registered source', function () {
    $sources = app(MigrationRegistry::class)->all();

    expect($sources)->toHaveCount(39)
        ->and(array_map(fn ($source) => $source->key(), $sources))->toBe([
            'roles', 'users', 'business-functions', 'companies', 'company-sites', 'operational-sites',
            'business-function-members', 'referent-types', 'referents',
            'sources', 'tags', 'sectors', 'task-templates', 'vat-rates', 'payment-methods', 'attributes', 'product-categories',
            'product-category-attributes', 'attribute-layouts', 'products', 'cost-products', 'email-templates', 'document-bundles',
            'registries', 'opportunities', 'quotes', 'work-orders', 'tasks', 'work-order-progress',
            'product-suppliers', 'commission-configurations', 'purchase-requests', 'purchase-orders', 'financial-accounts', 'purchase-invoices', 'proforma-requests', 'invoices', 'collections',
            'notes',
        ]);
});
