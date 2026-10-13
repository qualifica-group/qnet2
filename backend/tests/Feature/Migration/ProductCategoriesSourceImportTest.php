<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\BusinessFunction;
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
                ['id' => 1, 'name' => '[OLD] Electronics', 'parent_id' => null, 'description' => 'Top level', 'requires_quote' => true, 'is_active' => false],
                ['id' => 2, 'name' => '[OLD] Laptops', 'parent_id' => 1, 'inherits_attributes' => false, 'is_selectable' => false, 'is_active' => false],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    $root = ProductCategory::query()->where('old_id', 1)->first();
    $child = ProductCategory::query()->where('old_id', 2)->first();

    expect($root->name)->toBe('[OLD] Electronics')
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
        ->and($child->name)->toBe('[OLD] Laptops')
        ->and($child->parent_id)->toBe($root->id)
        ->and($child->inherits_product_attributes)->toBeFalse()
        ->and($child->inherits_quote_attributes)->toBeFalse()
        ->and($child->inherits_work_order_attributes)->toBeFalse()
        // `is_selectable` is per-node (no inheritance), `requires_quote` is
        // taken from the branch root.
        ->and($child->is_selectable)->toBeFalse()
        ->and($child->requires_quote)->toBeTrue()
        // History only: the legacy sends every category as not active (user
        // directive 2026-10-13).
        ->and($root->is_active)->toBeFalse()
        ->and($child->is_active)->toBeFalse();

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->report)->toBeNull();
});

it('REQUIREMENT CHANGED: creates a child detached with a warning when its parent never resolves (no relink pass)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [['id' => 2, 'name' => 'Laptops', 'parent_id' => 99, 'requires_quote' => false]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    $fresh = $run->fresh();
    expect(ProductCategory::query()->where('old_id', 2)->first()->parent_id)->toBeNull()
        ->and($fresh->created_rows)->toBe(1)
        ->and(json_encode($fresh->report))->toContain('Unresolved parent_id');
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
        // A record that says nothing about it is imported active.
        ->and(ProductCategory::query()->where('name', 'Accessories')->value('is_active'))->toBeTruthy()
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

it('REQUIREMENT CHANGED (user directive 2026-10-13): never adopts a qnet category, the "[OLD] " twin is created beside it', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 20, 'name' => '[OLD] Consulenza', 'parent_id' => null, 'inherits_attributes' => true],
                ['id' => 21, 'name' => '[OLD] Presa Appuntamenti', 'parent_id' => 20, 'description' => 'Fissaggio appuntamenti', 'inherits_attributes' => false],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    // The state the static catalogue leaves: "Presa Appuntamenti" is a
    // selectable subcategory of "Consulenza", with no `old_id`.
    $consulenza = ProductCategory::factory()->create(['name' => 'Consulenza', 'parent_id' => null]);
    $seeded = ProductCategory::factory()->create([
        'name' => 'Presa Appuntamenti',
        'parent_id' => $consulenza->id,
        'is_selectable' => true,
        'description' => null,
        'inherits_product_attributes' => true,
        'inherits_quote_attributes' => true,
        'inherits_work_order_attributes' => true,
    ]);
    $before = $seeded->fresh()->only(['old_id', 'description', 'parent_id', 'is_selectable', 'inherits_product_attributes', 'inherits_quote_attributes', 'inherits_work_order_attributes']);

    $actor = migrationsSuperAdminActor();

    // Twice: the second run skips both rows by old_id.
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);
    runMigrationJobFor($run);
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    $legacyRoot = ProductCategory::query()->where('old_id', 20)->sole();
    $legacyChild = ProductCategory::query()->where('old_id', 21)->sole();

    expect($consulenza->fresh()->old_id)->toBeNull()
        ->and($seeded->fresh()->only(array_keys($before)))->toBe($before)
        ->and($legacyRoot->name)->toBe('[OLD] Consulenza')
        ->and($legacyRoot->parent_id)->toBeNull()
        ->and($legacyChild->name)->toBe('[OLD] Presa Appuntamenti')
        ->and($legacyChild->parent_id)->toBe($legacyRoot->id)
        ->and($legacyChild->description)->toBe('Fissaggio appuntamenti')
        ->and(ProductCategory::query()->count())->toBe(4)
        ->and($run->fresh()->created_rows)->toBe(2)
        ->and($run->fresh()->report)->toBeNull();
});

// ---------------------------------------------------------------------------
// business_function_id — remapped via old_id, one function per branch (spec 0023)
// ---------------------------------------------------------------------------

it('links the root to its business function and lets a same-function child inherit it', function () {
    seedMigrationsConfig();
    $function = BusinessFunction::factory()->create(['old_id' => 2]);

    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 1, 'name' => 'ISO', 'parent_id' => null, 'business_function_id' => 2],
                ['id' => 2, 'name' => '9001', 'parent_id' => 1, 'business_function_id' => 2],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    expect(ProductCategory::query()->where('old_id', 1)->value('business_function_id'))->toBe($function->id)
        // Inherited from the root: authoring it again would break the
        // one-function-per-branch invariant.
        ->and(ProductCategory::query()->where('old_id', 2)->value('business_function_id'))->toBeNull()
        ->and($run->fresh()->report)->toBeNull();
});

it('keeps the inherited function and warns when a child names a different one', function () {
    seedMigrationsConfig();
    BusinessFunction::factory()->create(['old_id' => 2]);
    BusinessFunction::factory()->create(['old_id' => 13]);

    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 1, 'name' => 'ISO', 'parent_id' => null, 'business_function_id' => 2],
                ['id' => 2, 'name' => 'FOR_Classi', 'parent_id' => 1, 'business_function_id' => 13],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    $fresh = $run->fresh();

    expect(ProductCategory::query()->where('old_id', 2)->value('business_function_id'))->toBeNull()
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->failed_rows)->toBe(0)
        ->and(collect($fresh->report)->firstWhere('level', 'warning'))->not->toBeNull();
});

it('creates the category without a function and warns when the function is not migrated', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [['id' => 1, 'name' => 'HACCP', 'parent_id' => null, 'business_function_id' => 23]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    $fresh = $run->fresh();

    expect(ProductCategory::query()->where('old_id', 1)->value('business_function_id'))->toBeNull()
        ->and($fresh->created_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'warning'))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Business functions — the legacy training tree stays on "Formazione", no
// "FORMAZIONE OLD" replacement (user directive 2026-10-13)
// ---------------------------------------------------------------------------

it('REQUIREMENT CHANGED (user directive 2026-10-13): files an imported category on the "Formazione" function as sent, with no "FORMAZIONE OLD" replacement', function () {
    seedMigrationsConfig();
    $imported = BusinessFunction::factory()->create(['old_id' => 2, 'name' => 'Formazione']);

    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 1, 'name' => '[OLD] FOR_Corsi', 'parent_id' => null, 'business_function_id' => 2, 'is_selectable' => true],
                ['id' => 2, 'name' => '[OLD] FOR_Classi', 'parent_id' => 1, 'business_function_id' => 2, 'is_selectable' => true],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);

    runMigrationJobFor($run);

    expect(BusinessFunction::query()->where('name', 'FORMAZIONE OLD')->exists())->toBeFalse()
        ->and(ProductCategory::query()->where('old_id', 1)->value('business_function_id'))->toBe($imported->id)
        // Same function on the child: it inherits, with no mismatch warning.
        ->and(ProductCategory::query()->where('old_id', 2)->value('business_function_id'))->toBeNull()
        ->and($run->fresh()->report)->toBeNull()
        // Outside the "[OLD] APL" branch the legacy selectability stands.
        ->and(ProductCategory::query()->where('old_id', 1)->value('is_selectable'))->toBeTruthy()
        ->and(ProductCategory::query()->where('old_id', 2)->value('is_selectable'))->toBeTruthy();
});

it('spec 0182 E-10: uses the quote/work-order flags when carried, the single flag otherwise', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 60, 'name' => 'PAL', 'parent_id' => null, 'inherits_attributes' => true,
                    'inherits_quote_attributes' => false, 'inherits_work_order_attributes' => false],
                ['id' => 61, 'name' => 'Legacy flag only', 'parent_id' => null, 'inherits_attributes' => false],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'product-categories']);
    runMigrationJobFor($run);

    $pal = ProductCategory::query()->where('old_id', 60)->first();
    $single = ProductCategory::query()->where('old_id', 61)->first();

    expect($pal->inherits_product_attributes)->toBeTrue()
        ->and($pal->inherits_quote_attributes)->toBeFalse()
        ->and($pal->inherits_work_order_attributes)->toBeFalse()
        ->and($single->inherits_product_attributes)->toBeFalse()
        ->and($single->inherits_quote_attributes)->toBeFalse()
        ->and($single->inherits_work_order_attributes)->toBeFalse();
});

it('REQUIREMENT CHANGED (spec 0183 F-9): a self-referencing root arrives with a null parent_id and is imported as a root, idempotently', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [['id' => 2, 'name' => 'SOA', 'parent_id' => null, 'inherits_attributes' => true]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    foreach ([1, 2] as $pass) {
        $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'product-categories']);
        runMigrationJobFor($run);
    }

    expect(ProductCategory::query()->where('name', 'SOA')->count())->toBe(1)
        ->and(ProductCategory::query()->where('old_id', 2)->first()->parent_id)->toBeNull()
        ->and(json_encode($run->fresh()->report))->not->toContain('detached');
});
