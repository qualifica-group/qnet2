<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\Source;
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
// SourcesSource — create + old_id (phase-1 lookup anchor)
// ---------------------------------------------------------------------------

it('creates sources with their old_id', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/sources*' => Http::response([
            'items' => [
                ['id' => 3, 'name' => 'Website'],
                ['id' => 4, 'name' => 'Trade fair'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sources']);

    runMigrationJobFor($run);

    expect(Source::query()->where('old_id', 3)->value('name'))->toBe('Website')
        ->and(Source::query()->where('old_id', 4)->value('name'))->toBe('Trade fair');

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->report)->toBeNull();
});

it('re-importing the same sources is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/sources*' => Http::response([
            'items' => [['id' => 9, 'name' => 'Referral']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sources']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sources']);
    runMigrationJobFor($secondRun);

    expect(Source::query()->where('name', 'Referral')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

it('adopts a source already provisioned under the same name (no duplicate)', function () {
    seedMigrationsConfig();
    // What the static template seed leaves behind: a name, no old_id.
    $existing = Source::query()->create(['name' => 'Passaparola']);
    Http::fake([
        fakeMigrationsBaseUrl().'/sources*' => Http::response([
            'items' => [
                ['id' => 21, 'name' => 'Passaparola'],
                ['id' => 22, 'name' => 'Fiera'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sources']);

    runMigrationJobFor($run);

    expect(Source::query()->where('name', 'Passaparola')->count())->toBe(1)
        ->and($existing->fresh()->old_id)->toBe(21)
        // The legacy-only name is still created alongside the adopted one.
        ->and(Source::query()->where('old_id', 22)->value('name'))->toBe('Fiera')
        ->and($run->fresh()->created_rows)->toBe(2);
});

it('never adopts a source already claimed by another external id', function () {
    seedMigrationsConfig();
    $claimed = Source::query()->create(['name' => 'Sito']);
    $claimed->old_id = 30;
    $claimed->save();

    Http::fake([
        fakeMigrationsBaseUrl().'/sources*' => Http::response([
            'items' => [['id' => 31, 'name' => 'Sito']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sources']));

    expect(Source::query()->where('name', 'Sito')->count())->toBe(2)
        ->and($claimed->fresh()->old_id)->toBe(30);
});

it('isolates a failed source row (missing name) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/sources*' => Http::response([
            'items' => [
                ['id' => 10, 'name' => ''],
                ['id' => 11, 'name' => 'Valid Source'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'sources']);

    runMigrationJobFor($run);

    expect(Source::query()->where('name', 'Valid Source')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});
