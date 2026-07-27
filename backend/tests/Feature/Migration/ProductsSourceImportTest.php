<?php

use App\Enums\MigrationStatus;
use App\Enums\ProductType;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\Product;
use App\Models\ProductCategory;
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
if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('seedMigrationsConfig')) {
    function seedMigrationsConfig(): void
    {
        config([
            'migrations.base_url' => fakeMigrationsBaseUrl(),
            'migrations.token' => null,
            'migrations.timeout' => 5,
            'migrations.retry_times' => 1,
            'migrations.retry_sleep_ms' => 1,
            'migrations.import_batch_size' => 100,
        ]);
    }
}

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
    ProductCategory::factory()->create(['old_id' => 5, 'name' => 'Consulting']);

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Onboarding package',
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

    expect($product->name)->toBe('Onboarding package')
        ->and($product->description)->toBe('Setup service')
        ->and((float) $product->cost)->toBe(100.0)
        ->and((float) $product->price)->toBe(250.5)
        ->and($product->category_id)->toBe($category->id)
        ->and($product->product_type)->toBe(ProductType::Service)
        ->and($product->vat_rate_id)->toBeNull()
        ->and($product->supplier_id)->toBeNull();

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

it('warns and nulls an unmigrated vat_rate_id and the un-remappable supplier_id', function () {
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
        ->and($product->supplier_id)->toBeNull();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and(collect($fresh->report)->where('level', 'warning'))->toHaveCount(2);
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
