<?php

use App\Migrations\Sources\AttributesSource;
use App\Migrations\Sources\BusinessFunctionMembersSource;
use App\Migrations\Sources\BusinessFunctionsSource;
use App\Migrations\Sources\CompaniesSource;
use App\Migrations\Sources\OperationalSitesSource;
use App\Migrations\Sources\ProductCategoriesSource;
use App\Migrations\Sources\ProductCategoryAttributesSource;
use App\Migrations\Sources\ProductsSource;
use App\Migrations\Sources\ReferentsSource;
use App\Migrations\Sources\ReferentTypesSource;
use App\Migrations\Sources\RolesSource;
use App\Migrations\Sources\SectorsSource;
use App\Migrations\Sources\SourcesSource;
use App\Migrations\Sources\TagsSource;
use App\Migrations\Sources\UsersSource;

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
        'operational-sites' => OperationalSitesSource::class,
        'business-function-members' => BusinessFunctionMembersSource::class,
        'referent-types' => ReferentTypesSource::class,
        'referents' => ReferentsSource::class,
        'sources' => SourcesSource::class,
        'tags' => TagsSource::class,
        'sectors' => SectorsSource::class,
        'attributes' => AttributesSource::class,
        'product-categories' => ProductCategoriesSource::class,
        'product-category-attributes' => ProductCategoryAttributesSource::class,
        'products' => ProductsSource::class,
    ],

];
