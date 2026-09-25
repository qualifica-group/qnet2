<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\City;
use App\Models\Country;
use App\Models\MigrationRun;
use App\Models\OperationalSite;
use App\Models\Province;
use App\Models\Role;
use App\Models\State;
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
// OperationalSitesSource — create + address (geo resolved by name)
// ---------------------------------------------------------------------------

it('creates a site with its address, resolving geo names to ids', function () {
    seedMigrationsConfig();
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->for($country)->create(['name' => 'Lazio']);
    $city = City::factory()->forState($state)->create(['name' => 'Rome']);

    Http::fake([
        fakeMigrationsBaseUrl().'/operational-sites*' => Http::response([
            'items' => [[
                'id' => 1,
                'country' => 'Italy',
                'region' => 'Lazio',
                'city' => 'Rome',
                'street' => 'Via Roma 1',
                'postal_code' => '00100',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'operational-sites']);

    runMigrationJobFor($run);

    $site = OperationalSite::query()->where('old_id', 1)->first();

    expect($site)->not->toBeNull();

    $address = $site->addresses()->first();
    expect($address)->not->toBeNull()
        ->and($address->line1)->toBe('Via Roma 1')
        ->and($address->country_id)->toBe($country->id)
        ->and($address->state_id)->toBe($state->id)
        ->and($address->city_id)->toBe($city->id);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->report)->toBeNull();
});

it('stores the legacy comune as alias and resolves Italian names + province code', function () {
    seedMigrationsConfig();
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->for($country)->create(['name' => 'Campania']);
    $province = Province::factory()->forState($state)->create(['name' => 'Naples']);
    $city = City::factory()->forProvince($province)->create(['name' => 'Frattamaggiore']);

    Http::fake([
        fakeMigrationsBaseUrl().'/operational-sites*' => Http::response([
            'items' => [[
                'id' => 10,
                'country' => 'Italia',
                'region' => 'Campania',
                'province' => 'NA',
                'city' => 'FRATTAMAGGIORE 1 (HQ)',
                'street' => 'Via Roma 1',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'operational-sites']);

    runMigrationJobFor($run);

    $site = OperationalSite::query()->where('old_id', 10)->first();

    expect($site)->not->toBeNull()
        ->and($site->alias)->toBe('FRATTAMAGGIORE 1 (HQ)');

    $address = $site->addresses()->first();
    expect($address->country_id)->toBe($country->id)
        ->and($address->state_id)->toBe($state->id)
        ->and($address->province_id)->toBe($province->id)
        ->and($address->city_id)->toBe($city->id);

    expect($run->fresh()->report)->toBeNull();
});

it('creates the site with a warning when the region name cannot be resolved', function () {
    seedMigrationsConfig();
    Country::factory()->create(['name' => 'Italy']);

    Http::fake([
        fakeMigrationsBaseUrl().'/operational-sites*' => Http::response([
            'items' => [[
                'id' => 2,
                'country' => 'Italy',
                'region' => 'Unknown Region',
                'street' => 'Via Milano 5',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'operational-sites']);

    runMigrationJobFor($run);

    $site = OperationalSite::query()->where('old_id', 2)->first();

    expect($site)->not->toBeNull();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->report)->not->toBeNull()
        ->and($fresh->report[0]['level'])->toBe('warning')
        ->and($fresh->report[0]['message'])->toContain('Unknown Region');
});

it('isolates a failed row (missing street) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/operational-sites*' => Http::response([
            'items' => [
                ['id' => 3],
                ['id' => 4, 'street' => 'Via Torino 9'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'operational-sites']);

    runMigrationJobFor($run);

    expect(OperationalSite::query()->where('old_id', 4)->exists())->toBeTrue()
        ->and(OperationalSite::query()->where('old_id', 3)->exists())->toBeFalse();

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});

it('re-importing the same sites is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/operational-sites*' => Http::response([
            'items' => [['id' => 5, 'street' => 'Via Napoli 2']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $firstRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'operational-sites']);
    runMigrationJobFor($firstRun);

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'operational-sites']);
    runMigrationJobFor($secondRun);

    expect(OperationalSite::query()->where('old_id', 5)->count())->toBe(1);

    $fresh = $secondRun->fresh();
    expect($fresh->skipped_rows)->toBe(1)
        ->and($fresh->created_rows)->toBe(0);
});
