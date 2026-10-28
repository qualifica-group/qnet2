<?php

use App\Enums\MigrationStatus;
use App\Enums\ProductType;
use App\Jobs\RunMigrationJob;
use App\Migrations\Sources\ProductsSource;
use App\Models\MigrationRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Role;
use App\Models\User;
use App\Models\VatRate;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// The shared helpers (fakeMigrationsBaseUrl/seedMigrationsConfig/
// migrationsSuperAdminActor/runMigrationJobFor) are defined once, guarded by
// function_exists, across the Migration feature suite (see CompaniesSourceImportTest).

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationService::class));
    }
}

// ---------------------------------------------------------------------------
// ProductsSource — create + old_id + required category remap
// ---------------------------------------------------------------------------

it('creates a product, remapping the required category_id via old_id', function () {
    seedMigrationsConfig();
    // A category already migrated: its external id (5) is what a product points at.
    ProductCategory::factory()->create(['old_id' => 5, 'name' => '[OLD] Consulting']);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [
                [
                    'id' => 1,
                    'name' => '[OLD] Onboarding package',
                    'description' => 'Setup service',
                    'cost' => 100,
                    'price' => 250.5,
                    'category_id' => 5,
                    'product_type' => 'SERVICE',
                ],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'products']);

    runMigrationJobFor($run);

    $product = Product::query()->where('old_id', 1)->first();
    $category = ProductCategory::query()->where('old_id', 5)->first();

    expect($product->name)->toBe('[OLD] Onboarding package')
        ->and($product->description)->toBe('Setup service')
        ->and((float) $product->cost)->toBe(100.0)
        ->and((float) $product->price)->toBe(250.5)
        ->and($product->category_id)->toBe($category->id)
        ->and($product->product_type)->toBe(ProductType::Service)
        ->and($product->vat_rate_id)->toBeNull()
        ->and($product->productSuppliers()->count())->toBe(0);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->report)->toBeNull();
});

it('remaps vat_rate_id onto the migrated VAT rate via old_id', function () {
    seedMigrationsConfig();
    ProductCategory::factory()->create(['old_id' => 5]);
    $vatRate = VatRate::factory()->create(['old_id' => 9]);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [
                ['id' => 3, 'name' => 'Audit', 'category_id' => 5, 'vat_rate_id' => 9],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'products']);

    runMigrationJobFor($run);

    expect(Product::query()->where('old_id', 3)->value('vat_rate_id'))->toBe($vatRate->id)
        ->and($run->fresh()->report)->toBeNull();
});

it('warns and nulls an unmigrated vat_rate_id and never writes the legacy supplier', function () {
    seedMigrationsConfig();
    ProductCategory::factory()->create(['old_id' => 5]);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [
                ['id' => 2, 'name' => 'Support plan', 'category_id' => 5, 'vat_rate_id' => 9, 'supplier_id' => 12],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'products']);

    runMigrationJobFor($run);

    $product = Product::query()->where('old_id', 2)->first();

    expect($product->vat_rate_id)->toBeNull()
        ->and($product->productSuppliers()->count())->toBe(0);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and(collect($fresh->report)->where('level', 'warning'))->toHaveCount(1);
});

it('defaults an absent product_type and warns on an unknown one', function () {
    seedMigrationsConfig();
    ProductCategory::factory()->create(['old_id' => 5]);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [
                ['id' => 3, 'name' => 'No type', 'category_id' => 5],
                ['id' => 4, 'name' => 'Bad type', 'category_id' => 5, 'product_type' => 'GOODS'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'products']);

    runMigrationJobFor($run);

    expect(Product::query()->where('old_id', 3)->value('product_type'))->toBe(ProductType::Service)
        ->and(Product::query()->where('old_id', 4)->value('product_type'))->toBe(ProductType::Service);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(2)
        ->and(collect($fresh->report)->firstWhere('level', 'warning'))->not->toBeNull();
});

it('maps the legacy folder onto the product typology by name, case-insensitively', function () {
    seedMigrationsConfig();
    ProductCategory::factory()->create(['old_id' => 5]);
    $institution = ProductTypology::query()->where('code', 'institution')->firstOrFail();
    $consultancy = ProductTypology::factory()->create(['code' => 'consultancy', 'name' => 'Consulenza']);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [
                ['id' => 1, 'name' => 'Consulting', 'category_id' => 5, 'folder' => 'Consulenza'],
                ['id' => 2, 'name' => 'Certification', 'category_id' => 5, 'folder' => ' ente '],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'products']);

    runMigrationJobFor($run);

    expect(Product::query()->where('old_id', 1)->value('product_typology_id'))->toBe($consultancy->id)
        ->and(Product::query()->where('old_id', 2)->value('product_typology_id'))->toBe($institution->id)
        ->and($run->fresh()->report)->toBeNull();
});

it('defaults an absent folder and warns on one matching no typology', function () {
    seedMigrationsConfig();
    ProductCategory::factory()->create(['old_id' => 5]);
    $institution = ProductTypology::query()->where('code', 'institution')->firstOrFail();

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [
                ['id' => 3, 'name' => 'No folder', 'category_id' => 5],
                ['id' => 4, 'name' => 'Unknown folder', 'category_id' => 5, 'folder' => 'Archivio'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'products']);

    runMigrationJobFor($run);

    expect(Product::query()->where('old_id', 3)->value('product_typology_id'))->toBe($institution->id)
        ->and(Product::query()->where('old_id', 4)->value('product_typology_id'))->toBe($institution->id);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(2)
        ->and(collect($fresh->report)->where('level', 'warning'))->toHaveCount(1);
});

it('cuts a name the "[OLD] " prefix pushed past the column to its 191 characters (user directive 2026-10-13)', function () {
    seedMigrationsConfig();
    ProductCategory::factory()->create(['old_id' => 5]);
    $longName = '[OLD] '.str_repeat('a', 191);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [['id' => 1, 'name' => $longName, 'category_id' => 5, 'product_type' => 'SERVICE']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'products']);

    runMigrationJobFor($run);

    expect(Product::query()->where('old_id', 1)->value('name'))->toBe(mb_substr($longName, 0, 191))
        ->and($run->fresh()->created_rows)->toBe(1);
});

it('re-importing the same product is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    ProductCategory::factory()->create(['old_id' => 5]);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [['id' => 7, 'name' => 'Retainer', 'category_id' => 5]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'products']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'products']);
    runMigrationJobFor($secondRun);

    expect(Product::query()->where('name', 'Retainer')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

it('fails a product whose required category is not migrated, without blocking a valid one', function () {
    seedMigrationsConfig();
    ProductCategory::factory()->create(['old_id' => 5]);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [
                // category_id 99 was never migrated -> fatal per-row, isolated.
                ['id' => 10, 'name' => 'Orphan', 'category_id' => 99],
                ['id' => 11, 'name' => 'Valid product', 'category_id' => 5],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'products']);

    runMigrationJobFor($run);

    expect(Product::query()->where('name', 'Valid product')->exists())->toBeTrue()
        ->and(Product::query()->where('name', 'Orphan')->exists())->toBeFalse();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});

it('REQUIREMENT CHANGED (user directive 2026-10-13): keeps a product of the legacy "[OLD] APL" branch on its own legacy node, never on the manual one', function () {
    seedMigrationsConfig();
    $manualRoot = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => null]);
    $manualTarget = ProductCategory::factory()->create(['name' => 'Apprendistato', 'parent_id' => $manualRoot->id]);

    // The legacy branch as the import leaves it: under "Consulenza", history only.
    $consulenza = ProductCategory::factory()->create(['name' => 'Consulenza', 'parent_id' => null]);
    $legacyRoot = ProductCategory::factory()->create(['old_id' => 10, 'name' => '[OLD] APL', 'parent_id' => $consulenza->id, 'is_active' => false]);
    $legacyNode = ProductCategory::factory()->create(['old_id' => 11, 'name' => '[OLD] Formazione Apprendistato', 'parent_id' => $legacyRoot->id, 'is_active' => false]);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [['id' => 1, 'name' => '[OLD] Product 1', 'category_id' => 11, 'product_type' => 'SERVICE']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'products']);

    runMigrationJobFor($run);

    expect(Product::query()->where('old_id', 1)->value('category_id'))->toBe($legacyNode->id)
        ->and(Product::query()->where('category_id', $manualTarget->id)->exists())->toBeFalse()
        ->and($run->fresh()->created_rows)->toBe(1)
        ->and($run->fresh()->report)->toBeNull();
});

it('files a product the legacy left without a category under "No categoria", with a warning', function () {
    seedMigrationsConfig();
    $uncategorized = ProductCategory::factory()->create(['name' => ProductsSource::UNCATEGORIZED_CATEGORY, 'is_selectable' => false]);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [['id' => 499, 'name' => 'Sconto a lei riservato', 'price' => -400, 'category_id' => null]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'products']);
    runMigrationJobFor($run);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(0)
        ->and(Product::query()->where('old_id', 499)->value('category_id'))->toBe($uncategorized->id)
        // History only, like the rest of the migrated catalogue (user
        // directive 2026-10-13).
        ->and($uncategorized->fresh()->is_active)->toBeFalse()
        ->and(collect($fresh->report)->pluck('message')->implode(' '))->toContain("filed under 'No categoria'");
});

it('fails a product without a category when the "No categoria" category was never seeded', function () {
    seedMigrationsConfig();

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [['id' => 499, 'name' => 'Sconto a lei riservato', 'category_id' => null]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'products']);
    runMigrationJobFor($run);

    $fresh = $run->fresh();
    expect($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->pluck('message')->implode(' '))->toContain('run QualificaCatalogSeeder first');
});
