<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\BusinessFunction;
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
// AC-010 — BusinessFunctionsSource: create + attach users via old_id + warning
// ---------------------------------------------------------------------------

it('creates a business function and attaches users resolved via old_id', function () {
    seedMigrationsConfig();
    $memberOne = User::factory()->create(['old_id' => 501]);
    $memberTwo = User::factory()->create(['old_id' => 502]);

    Http::fake([
        fakeMigrationsBaseUrl().'/business-functions*' => Http::response([
            'items' => [
                ['id' => 1, 'name' => 'Finance', 'type' => 'business_unit', 'user_ids' => [501, 502]],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'business-functions']);

    runMigrationJobFor($run);

    $businessFunction = BusinessFunction::query()->where('name', 'Finance')->first();

    expect($businessFunction)->not->toBeNull()
        ->and($businessFunction->old_id)->toBe(1)
        ->and($businessFunction->is_business_unit)->toBeTrue()
        ->and($businessFunction->users->pluck('id')->sort()->values()->all())
        ->toBe(collect([$memberOne->id, $memberTwo->id])->sort()->values()->all());

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->skipped_rows)->toBe(0);
});

it('creates the business function even when a user reference is unresolved, with a warning', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/business-functions*' => Http::response([
            'items' => [
                ['id' => 2, 'name' => 'Operations', 'user_ids' => [999]],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'business-functions']);

    runMigrationJobFor($run);

    $businessFunction = BusinessFunction::query()->where('name', 'Operations')->first();

    expect($businessFunction)->not->toBeNull()
        ->and($businessFunction->old_id)->toBe(2)
        ->and($businessFunction->users)->toHaveCount(0);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->report)->not->toBeNull()
        ->and($fresh->report[0]['level'])->toBe('warning')
        ->and($fresh->report[0]['message'])->toContain('999');
});

it('re-importing the same business functions is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/business-functions*' => Http::response([
            'items' => [
                ['id' => 3, 'name' => 'Already migrated'],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $firstRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'business-functions']);
    runMigrationJobFor($firstRun);

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'business-functions']);
    runMigrationJobFor($secondRun);

    expect(BusinessFunction::query()->where('name', 'Already migrated')->count())->toBe(1);

    $fresh = $secondRun->fresh();
    expect($fresh->skipped_rows)->toBe(1)
        ->and($fresh->created_rows)->toBe(0);
});
