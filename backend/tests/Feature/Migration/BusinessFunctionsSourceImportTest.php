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

// ---------------------------------------------------------------------------
// User directive 2026-10-14 — the seeded "Formazione" / "APL" functions are
// adopted by name, compared without case, never duplicated
// ---------------------------------------------------------------------------

it('adopts an unclaimed function of the same name in another case, keeping its name and adding the resolved users', function () {
    seedMigrationsConfig();
    $seeded = BusinessFunction::factory()->create(['name' => 'Formazione']);
    $existingMember = User::factory()->create();
    $seeded->users()->attach($existingMember->id);
    $legacyMember = User::factory()->create(['old_id' => 601]);

    Http::fake([
        fakeMigrationsBaseUrl().'/business-functions*' => Http::response([
            'items' => [['id' => 13, 'name' => 'FORMAZIONE', 'user_ids' => [601]]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'business-functions']);
    runMigrationJobFor($run);
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'business-functions']));

    expect(BusinessFunction::query()->count())->toBe(1)
        ->and($seeded->fresh()->old_id)->toBe(13)
        ->and($seeded->fresh()->name)->toBe('Formazione')
        ->and($seeded->users()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$existingMember->id, $legacyMember->id])->sort()->values()->all())
        ->and($run->fresh()->created_rows)->toBe(1);
});

it('never adopts a function another external id already claimed', function () {
    seedMigrationsConfig();
    $claimed = BusinessFunction::factory()->create(['name' => 'APL', 'old_id' => 8]);

    Http::fake([
        fakeMigrationsBaseUrl().'/business-functions*' => Http::response([
            'items' => [['id' => 30, 'name' => 'APL']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'business-functions']));

    expect(BusinessFunction::query()->where('name', 'APL')->count())->toBe(2)
        ->and($claimed->fresh()->old_id)->toBe(8)
        ->and(BusinessFunction::query()->where('old_id', 30)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// User decision 2026-10-19 — the legacy sends the functions outside the
// organisation chart as "[OLD] ..." and not active: created switched off
// ---------------------------------------------------------------------------

it('creates a function sent as not active switched off, and adopts the chart one by name', function () {
    seedMigrationsConfig();
    $chart = BusinessFunction::factory()->create(['name' => 'Agenzia per il Lavoro']);

    Http::fake([
        fakeMigrationsBaseUrl().'/business-functions*' => Http::response([
            'items' => [
                ['id' => 8, 'name' => 'Agenzia per il Lavoro', 'is_active' => true],
                ['id' => 6, 'name' => '[OLD] IT', 'is_active' => false],
                ['id' => 40, 'name' => 'Without flag'],
            ],
            'pagination' => ['total' => 3],
        ]),
    ]);

    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'business-functions']));

    expect($chart->fresh()->old_id)->toBe(8)
        ->and($chart->fresh()->is_active)->toBeTrue()
        ->and(BusinessFunction::query()->where('old_id', 6)->first())
        ->name->toBe('[OLD] IT')
        ->is_active->toBeFalse()
        ->and(BusinessFunction::query()->where('old_id', 40)->value('is_active'))->toBeTrue()
        ->and(BusinessFunction::query()->count())->toBe(3);
});

it('keeps the own flag of an adopted function, whatever the legacy sends', function () {
    seedMigrationsConfig();
    $switchedOff = BusinessFunction::factory()->inactive()->create(['name' => 'Gare']);

    Http::fake([
        fakeMigrationsBaseUrl().'/business-functions*' => Http::response([
            'items' => [['id' => 17, 'name' => 'Gare', 'is_active' => true]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'business-functions']));

    expect($switchedOff->fresh()->old_id)->toBe(17)
        ->and($switchedOff->fresh()->is_active)->toBeFalse();
});
