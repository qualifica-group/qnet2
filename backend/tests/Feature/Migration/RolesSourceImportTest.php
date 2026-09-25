<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

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
// AC-011 — RolesSource: adoption on existing name, creation, no permissions
// ---------------------------------------------------------------------------

it('adopts old_id onto an existing role sharing the same name (no duplicate)', function () {
    seedMigrationsConfig();
    $existing = Role::factory()->create(['name' => 'operator']);
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 1, 'name' => 'operator', 'description' => 'Front-line operator']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);

    runMigrationJobFor($run);

    expect(Role::query()->where('name', 'operator')->count())->toBe(1)
        ->and($existing->fresh()->old_id)->toBe(1)
        // Backfilled onto the adopted role, which had no description yet.
        ->and($existing->fresh()->description)->toBe('Front-line operator');

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->skipped_rows)->toBe(0);
});

it('creates a new role with old_id and description when the name does not exist yet', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 2, 'name' => 'brand-new-role', 'description' => 'A freshly imported role']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);

    runMigrationJobFor($run);

    $role = Role::query()->where('name', 'brand-new-role')->first();

    expect($role)->not->toBeNull()
        ->and($role->old_id)->toBe(2)
        ->and($role->description)->toBe('A freshly imported role')
        ->and($role->permissions)->toHaveCount(0); // AC-011: permissions are never imported
});

it('never clobbers a curated description when adopting an existing role', function () {
    seedMigrationsConfig();
    $existing = Role::factory()->create(['name' => 'curator', 'description' => 'Curated in qnet']);
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 4, 'name' => 'curator', 'description' => 'External description']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);

    runMigrationJobFor($run);

    expect($existing->fresh()->old_id)->toBe(4)
        ->and($existing->fresh()->description)->toBe('Curated in qnet');
});

it('re-importing the same roles is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 3, 'name' => 'already-migrated']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $firstRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);
    runMigrationJobFor($firstRun);

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);
    runMigrationJobFor($secondRun);

    expect(Role::query()->where('name', 'already-migrated')->count())->toBe(1);

    $fresh = $secondRun->fresh();
    expect($fresh->skipped_rows)->toBe(1)
        ->and($fresh->created_rows)->toBe(0);
});
