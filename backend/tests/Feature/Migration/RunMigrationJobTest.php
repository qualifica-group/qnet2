<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
// AC-012 — a single row's commit-time failure is isolated, others proceed
// ---------------------------------------------------------------------------

it('isolates a per-row commit-time failure without blocking the other rows', function () {
    seedMigrationsConfig();
    $existing = User::factory()->create(['email' => 'collision@example.test']);
    $password = Hash::make('external-secret');

    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [
                ['id' => 301, 'email' => 'valid-one@example.test', 'password' => $password, 'first_name' => 'One', 'last_name' => 'Ok'],
                // Duplicates an EXISTING (non-migrated) user's email: the
                // UserService::create() unique-email constraint fails at
                // commit time, isolated to this row only.
                ['id' => 302, 'email' => $existing->email, 'password' => $password, 'first_name' => 'Two', 'last_name' => 'Bad'],
                ['id' => 303, 'email' => 'valid-three@example.test', 'password' => $password, 'first_name' => 'Three', 'last_name' => 'Ok'],
            ],
            'pagination' => ['total' => 3],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);

    runMigrationJobFor($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->failed_rows)->toBe(1)
        ->and($fresh->report)->not->toBeNull();

    $errorEntry = collect($fresh->report)->firstWhere('level', 'error');
    expect($errorEntry['old_id'])->toBe(302);

    expect(User::query()->where('email', 'valid-one@example.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'valid-three@example.test')->exists())->toBeTrue()
        ->and(User::query()->where('old_id', 302)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-013 — an unhandled exception moves the run to failed
// ---------------------------------------------------------------------------

it('moves the run to failed on an unhandled exception (e.g. unregistered source)', function () {
    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create([
        'user_id' => $actor->id,
        'source' => 'not-a-registered-source',
        'status' => MigrationStatus::Pending,
    ]);

    expect(fn () => runMigrationJobFor($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(MigrationStatus::Failed);
});
