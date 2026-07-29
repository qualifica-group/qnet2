<?php

use App\Migrations\MigrationRegistry;
use App\Migrations\Sources\AttributesSource;
use App\Migrations\Sources\BusinessFunctionMembersSource;
use App\Migrations\Sources\BusinessFunctionsSource;
use App\Migrations\Sources\CompaniesSource;
use App\Migrations\Sources\CompanySitesSource;
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
use App\Migrations\Sources\VatRatesSource;
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
        'vat-rates' => VatRatesSource::class,
        'attributes' => AttributesSource::class,
        'product-categories' => ProductCategoriesSource::class,
        'product-category-attributes' => ProductCategoryAttributesSource::class,
        'products' => ProductsSource::class,
    ]);
});

it('all() resolves every registered source', function () {
    $sources = app(MigrationRegistry::class)->all();

    expect($sources)->toHaveCount(17)
        ->and(array_map(fn ($source) => $source->key(), $sources))->toBe([
            'roles', 'users', 'business-functions', 'companies', 'company-sites', 'operational-sites',
            'business-function-members', 'referent-types', 'referents',
            'sources', 'tags', 'sectors', 'vat-rates', 'attributes', 'product-categories',
            'product-category-attributes', 'products',
        ]);
});
