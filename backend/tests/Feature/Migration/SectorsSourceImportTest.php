<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\Sector;
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
// SectorsSource — self-referential tree: create + old_id + parent remap
// ---------------------------------------------------------------------------

it('creates root and child sectors, remapping parent_id via old_id', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/sectors*' => Http::response([
            'items' => [
                ['id' => 1, 'name' => 'Manufacturing', 'parent_id' => null],
                ['id' => 2, 'name' => 'Automotive', 'parent_id' => 1],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sectors']);

    runMigrationJobFor($run);

    $root = Sector::query()->where('old_id', 1)->first();
    $child = Sector::query()->where('old_id', 2)->first();

    expect($root->name)->toBe('Manufacturing')
        ->and($root->parent_id)->toBeNull()
        ->and($child->name)->toBe('Automotive')
        ->and($child->parent_id)->toBe($root->id);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->report)->toBeNull();
});

it('relinks a child listed before its parent (forward reference) via afterImport', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/sectors*' => Http::response([
            'items' => [
                // Child first: its parent is not migrated yet at processRow time.
                ['id' => 2, 'name' => 'Automotive', 'parent_id' => 1],
                ['id' => 1, 'name' => 'Manufacturing', 'parent_id' => null],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sectors']);

    runMigrationJobFor($run);

    $root = Sector::query()->where('old_id', 1)->first();
    $child = Sector::query()->where('old_id', 2)->first();

    // afterImport resolved the forward reference once the parent existed.
    expect($child->parent_id)->toBe($root->id);

    // The detached-then-relinked child still surfaced a non-fatal warning.
    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(2)
        ->and(collect($fresh->report)->firstWhere('level', 'warning'))->not->toBeNull();
});

it('re-importing the same sectors is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/sectors*' => Http::response([
            'items' => [['id' => 7, 'name' => 'Services', 'parent_id' => null]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sectors']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sectors']);
    runMigrationJobFor($secondRun);

    expect(Sector::query()->where('name', 'Services')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

it('isolates a failed sector row (missing name) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/sectors*' => Http::response([
            'items' => [
                ['id' => 10, 'name' => '', 'parent_id' => null],
                ['id' => 11, 'name' => 'Valid Sector', 'parent_id' => null],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sectors']);

    runMigrationJobFor($run);

    expect(Sector::query()->where('name', 'Valid Sector')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});
