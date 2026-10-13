<?php

use App\Jobs\RunMigrationJob;
use App\Migrations\Support\LegacyAplBranch;
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
// The legacy APL tree — sent as "[OLD] APL" and imported beside the manual
// "APL" branch, never into it (user directives 2026-10-05 and 2026-10-13,
// LegacyAplBranch)
// ---------------------------------------------------------------------------

it('REQUIREMENT CHANGED (user directive 2026-10-13): imports the "[OLD] APL" tree beside the manual one, under its legacy parent, with the names as sent, idempotently', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 60, 'name' => '[OLD] Servizi', 'parent_id' => null],
                ['id' => 61, 'name' => '[OLD] APL', 'parent_id' => 60],
                ['id' => 62, 'name' => '[OLD] Orientamento Specialistico', 'parent_id' => 61],
                ['id' => 63, 'name' => '[OLD] Tirocini extracurriculari privati', 'parent_id' => 61],
            ],
            'pagination' => ['total' => 4],
        ]),
    ]);

    // The manual branch QualificaCatalogSeeder leaves.
    $manualRoot = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => null]);
    $manualChild = ProductCategory::factory()->create(['name' => 'Orientamento specialistico', 'parent_id' => $manualRoot->id]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']);
    runMigrationJobFor($run);
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    $legacyTop = ProductCategory::query()->where('old_id', 61)->sole();

    expect($manualRoot->fresh()->old_id)->toBeNull()
        ->and($manualChild->fresh()->old_id)->toBeNull()
        // The twin of the manual root hangs under its legacy parent, like any
        // other legacy node (the seed then nests that under "Consulenza").
        ->and($legacyTop->name)->toBe(LegacyAplBranch::LEGACY_BRANCH)
        ->and($legacyTop->parent_id)->toBe(ProductCategory::query()->where('old_id', 60)->value('id'))
        ->and(ProductCategory::query()->where('old_id', 62)->first()->only(['name', 'parent_id']))
        ->toBe(['name' => '[OLD] Orientamento Specialistico', 'parent_id' => $legacyTop->id])
        ->and(ProductCategory::query()->where('old_id', 63)->first()->only(['name', 'parent_id']))
        ->toBe(['name' => '[OLD] Tirocini extracurriculari privati', 'parent_id' => $legacyTop->id])
        ->and(ProductCategory::query()->count())->toBe(6)
        ->and($run->fresh()->report)->toBeNull();
});

it('REQUIREMENT CHANGED (user directive 2026-10-13): files an imported category on the "APL" function as sent, with no "APL OLD" replacement', function () {
    seedMigrationsConfig();
    $imported = BusinessFunction::factory()->create(['old_id' => 3, 'name' => 'APL']);

    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 1, 'name' => '[OLD] APL', 'parent_id' => null, 'business_function_id' => 3],
                ['id' => 2, 'name' => '[OLD] Ricerca e Selezione', 'parent_id' => 1, 'business_function_id' => 3],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $manual = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => null, 'business_function_id' => $imported->id]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    expect(BusinessFunction::query()->where('name', 'APL OLD')->exists())->toBeFalse()
        ->and(ProductCategory::query()->where('old_id', 1)->value('business_function_id'))->toBe($imported->id)
        ->and(ProductCategory::query()->where('old_id', 2)->value('business_function_id'))->toBeNull()
        ->and($manual->fresh()->business_function_id)->toBe($imported->id);
});

it('imports the whole "[OLD] APL" branch as containers, and never reopens nor closes a node of an earlier run', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                // Selectable in the legacy: closed once it sits in the branch.
                ['id' => 71, 'name' => '[OLD] APL', 'parent_id' => null, 'is_selectable' => true],
                ['id' => 72, 'name' => '[OLD] Ricerca e Selezione', 'parent_id' => 71, 'is_selectable' => true],
                ['id' => 73, 'name' => '[OLD] Tirocinio', 'parent_id' => 71],
                ['id' => 74, 'name' => '[OLD] Bandi', 'parent_id' => null, 'is_selectable' => true],
            ],
            'pagination' => ['total' => 4],
        ]),
    ]);

    $manualRoot = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => null, 'is_selectable' => false]);
    $manualChild = ProductCategory::factory()->create(['name' => 'Tirocinio', 'parent_id' => $manualRoot->id, 'is_selectable' => true]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    $selectable = fn (int $oldId) => ProductCategory::query()->where('old_id', $oldId)->value('is_selectable');

    expect($selectable(71))->toBeFalsy()
        ->and($selectable(72))->toBeFalsy()
        ->and($selectable(73))->toBeFalsy()
        // Outside the legacy APL branch: the legacy value stands.
        ->and($selectable(74))->toBeTruthy()
        ->and($manualChild->fresh()->is_selectable)->toBeTruthy();

    // An operator reopens one node; a re-import leaves that choice alone.
    ProductCategory::query()->where('old_id', 72)->first()->update(['is_selectable' => true]);
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    expect($selectable(72))->toBeTruthy();
});
