<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
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
// ProductCategoriesSource — self-referential tree: create + old_id + parent remap
// ---------------------------------------------------------------------------

it('creates root and child categories, remapping parent_id via old_id', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 1, 'name' => 'Electronics', 'parent_id' => null, 'description' => 'Top level', 'requires_quote' => true],
                ['id' => 2, 'name' => 'Laptops', 'parent_id' => 1, 'inherits_attributes' => false, 'is_selectable' => false],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    $root = ProductCategory::query()->where('old_id', 1)->first();
    $child = ProductCategory::query()->where('old_id', 2)->first();

    expect($root->name)->toBe('Electronics')
        ->and($root->parent_id)->toBeNull()
        ->and($root->description)->toBe('Top level')
        // The external system has ONE inheritance flag; it seeds all three qnet
        // per-context barriers identically (Product / Offerta / Commessa).
        ->and($root->inherits_product_attributes)->toBeTrue()
        ->and($root->inherits_quote_attributes)->toBeTrue()
        ->and($root->inherits_work_order_attributes)->toBeTrue()
        // A root authors its own quote flag; `is_selectable` defaults to true
        // when the external record omits it.
        ->and($root->requires_quote)->toBeTrue()
        ->and($root->is_selectable)->toBeTrue()
        ->and($child->name)->toBe('Laptops')
        ->and($child->parent_id)->toBe($root->id)
        ->and($child->inherits_product_attributes)->toBeFalse()
        ->and($child->inherits_quote_attributes)->toBeFalse()
        ->and($child->inherits_work_order_attributes)->toBeFalse()
        // `is_selectable` is per-node (no inheritance), `requires_quote` is
        // taken from the branch root.
        ->and($child->is_selectable)->toBeFalse()
        ->and($child->requires_quote)->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->report)->toBeNull();
});

it('relinks a child listed before its parent (forward reference) via afterImport', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                // Child first: its parent is not migrated yet at processRow time.
                // It is created detached and authors requires_quote=false.
                ['id' => 2, 'name' => 'Laptops', 'parent_id' => 1, 'requires_quote' => false],
                ['id' => 1, 'name' => 'Electronics', 'parent_id' => null, 'requires_quote' => true],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    $root = ProductCategory::query()->where('old_id', 1)->first();
    $child = ProductCategory::query()->where('old_id', 2)->first();

    // afterImport resolved the forward reference once the parent existed, and
    // realigned the quote flag the detached child had authored on its own.
    expect($child->parent_id)->toBe($root->id)
        ->and($child->requires_quote)->toBeTrue();

    // The detached-then-relinked child still surfaced a non-fatal warning.
    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(2)
        ->and(collect($fresh->report)->firstWhere('level', 'warning'))->not->toBeNull();
});

it('re-importing the same categories is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [['id' => 7, 'name' => 'Accessories', 'parent_id' => null]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);
    runMigrationJobFor($secondRun);

    expect(ProductCategory::query()->where('name', 'Accessories')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

it('isolates a failed category row (missing name) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 10, 'name' => '', 'parent_id' => null],
                ['id' => 11, 'name' => 'Valid Category', 'parent_id' => null],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    expect(ProductCategory::query()->where('name', 'Valid Category')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});

it('adopts a category qnet already holds under that name instead of duplicating it', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 20, 'name' => 'APL', 'parent_id' => 99, 'description' => 'Agenzia per il lavoro', 'inherits_attributes' => false, 'is_selectable' => false],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    // The state the static catalogue leaves: "APL" is a selectable subcategory
    // of "Consulenza", with no `old_id`.
    $consulenza = ProductCategory::factory()->create(['name' => 'Consulenza', 'parent_id' => null]);
    $seeded = ProductCategory::factory()->create([
        'name' => 'APL',
        'parent_id' => $consulenza->id,
        'is_selectable' => true,
        'description' => null,
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    $adopted = $seeded->fresh();

    expect(ProductCategory::query()->where('name', 'APL')->count())->toBe(1)
        ->and($adopted->old_id)->toEqual(20)
        // Refreshed from the external record.
        ->and($adopted->description)->toBe('Agenzia per il lavoro')
        ->and($adopted->inherits_product_attributes)->toBeFalse()
        ->and($adopted->inherits_quote_attributes)->toBeFalse()
        ->and($adopted->inherits_work_order_attributes)->toBeFalse()
        // Left exactly as the catalogue authored it: adopting never MOVES a
        // node nor reopens a container.
        ->and($adopted->parent_id)->toBe($consulenza->id)
        ->and($adopted->is_selectable)->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(0);
});

it('adopts each name once: a second external id finds the slot taken and creates its own node', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 30, 'name' => 'APL', 'parent_id' => null],
                ['id' => 31, 'name' => 'APL', 'parent_id' => null],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $seeded = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => null]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    // The `old_id IS NULL` guard: the adopted row is claimed by 30, so 31 is a
    // genuinely distinct legacy category and gets its own node.
    expect($seeded->fresh()->old_id)->toEqual(30)
        ->and(ProductCategory::query()->where('old_id', 31)->exists())->toBeTrue()
        ->and(ProductCategory::query()->where('name', 'APL')->count())->toBe(2);
});

it('never adopts on a partial name match: adoption keys on the exact name', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [['id' => 40, 'name' => 'APL Servizi', 'parent_id' => null]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $seeded = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => null]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    expect($seeded->fresh()->old_id)->toBeNull()
        ->and(ProductCategory::query()->where('old_id', 40)->value('name'))->toBe('APL Servizi');
});

it('never relinks an ADOPTED root into the legacy tree, however many times the import runs', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 60, 'name' => 'Servizi', 'parent_id' => null],
                // The legacy twin of a qnet ROOT, filed under a legacy parent.
                ['id' => 61, 'name' => 'APL', 'parent_id' => 60],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    // The state the static catalogue leaves: "APL" is a root of its own.
    $seeded = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => null]);

    $actor = migrationsSuperAdminActor();

    // Twice: the first run adopts, the second skips by old_id — and the relink
    // pass runs on BOTH, so a root-level guard that only held within the
    // adopting run would let the second one move it.
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    expect($seeded->fresh()->old_id)->toEqual(61)
        // Adopted, so its position is qnet's: the external `parent_id: 60` has
        // no say over it.
        ->and($seeded->fresh()->parent_id)->toBeNull();
});
