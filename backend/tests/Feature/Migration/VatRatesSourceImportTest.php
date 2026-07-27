<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
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
// VatRatesSource — create + old_id (standalone settings lookup)
// ---------------------------------------------------------------------------

it('creates vat rates with their old_id', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/vat-rates*' => Http::response([
            'items' => [
                ['id' => 6, 'name' => 'IVA 22%', 'rate' => 22],
                ['id' => 7, 'name' => 'Esente', 'rate' => 0],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'vat-rates']);

    runMigrationJobFor($run);

    $standard = VatRate::query()->where('old_id', 6)->first();
    $exempt = VatRate::query()->where('old_id', 7)->first();

    expect($standard->name)->toBe('IVA 22%')
        ->and((float) $standard->rate)->toBe(22.0)
        ->and($exempt->name)->toBe('Esente')
        ->and((float) $exempt->rate)->toBe(0.0);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->report)->toBeNull();
});

it('re-importing the same vat rates is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/vat-rates*' => Http::response([
            'items' => [['id' => 9, 'name' => 'IVA 10%', 'rate' => 10]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'vat-rates']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'vat-rates']);
    runMigrationJobFor($secondRun);

    expect(VatRate::query()->where('name', 'IVA 10%')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

it('isolates a failed vat rate row (missing name) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/vat-rates*' => Http::response([
            'items' => [
                ['id' => 10, 'name' => '', 'rate' => 4],
                ['id' => 11, 'name' => 'IVA 4%', 'rate' => 4],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'vat-rates']);

    runMigrationJobFor($run);

    expect(VatRate::query()->where('name', 'IVA 4%')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});

it('fails the row when the rate is absent or not numeric, never inventing a percentage', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/vat-rates*' => Http::response([
            'items' => [
                ['id' => 12, 'name' => 'No rate'],
                ['id' => 13, 'name' => 'Bad rate', 'rate' => 'abc'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'vat-rates']);

    runMigrationJobFor($run);

    expect(VatRate::query()->count())->toBe(0)
        ->and($run->fresh()->failed_rows)->toBe(2);
});
