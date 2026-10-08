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
// The legacy APL tree — imported beside the manual "APL" branch as "APL old",
// never adopted into it (user directive 2026-10-05, LegacyAplBranch)
// ---------------------------------------------------------------------------

it('imports the legacy APL tree beside the manual one as "APL old", under its legacy parent, idempotently', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                // A child listed before its parent: relinked by afterImport.
                ['id' => 62, 'name' => 'Orientamento Specialistico', 'parent_id' => 61],
                ['id' => 60, 'name' => 'Servizi', 'parent_id' => null],
                ['id' => 61, 'name' => 'APL', 'parent_id' => 60],
                ['id' => 63, 'name' => 'Tirocini extracurriculari privati', 'parent_id' => 61],
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
        // The twin of a manual child takes the suffix; a name the manual
        // branch does not hold is kept.
        ->and(ProductCategory::query()->where('old_id', 62)->first()->only(['name', 'parent_id']))
        ->toBe(['name' => 'Orientamento Specialistico old', 'parent_id' => $legacyTop->id])
        ->and(ProductCategory::query()->where('old_id', 63)->first()->only(['name', 'parent_id']))
        ->toBe(['name' => 'Tirocini extracurriculari privati', 'parent_id' => $legacyTop->id])
        ->and(ProductCategory::query()->count())->toBe(6)
        ->and(collect($run->fresh()->report)->pluck('message')->filter(fn (string $message) => str_contains($message, 'Legacy twin'))->count())
        ->toBe(2);
});

it('files an imported category on "APL OLD" instead of the "APL" function', function () {
    seedMigrationsConfig();
    $imported = BusinessFunction::factory()->create(['old_id' => 3, 'name' => 'APL']);

    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 1, 'name' => 'APL', 'parent_id' => null, 'business_function_id' => 3],
                ['id' => 2, 'name' => 'Ricerca e Selezione', 'parent_id' => 1, 'business_function_id' => 3],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $manual = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => null, 'business_function_id' => $imported->id]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-categories']));

    $replacement = BusinessFunction::query()->where('name', 'APL OLD')->sole();

    expect($replacement->old_id)->toBeNull()
        ->and(ProductCategory::query()->where('old_id', 1)->value('business_function_id'))->toBe($replacement->id)
        ->and(ProductCategory::query()->where('old_id', 2)->value('business_function_id'))->toBeNull()
        ->and($manual->fresh()->business_function_id)->toBe($imported->id);
});

it('imports the whole "APL old" branch as containers, and never reopens nor closes a node of an earlier run', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                // Selectable in the legacy, and listed before its parent: closed
                // once the relink has placed it in the branch.
                ['id' => 72, 'name' => 'Ricerca e Selezione', 'parent_id' => 71, 'is_selectable' => true],
                ['id' => 71, 'name' => 'APL', 'parent_id' => null, 'is_selectable' => true],
                ['id' => 73, 'name' => 'Tirocinio', 'parent_id' => 71],
                ['id' => 74, 'name' => 'Bandi', 'parent_id' => null, 'is_selectable' => true],
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
