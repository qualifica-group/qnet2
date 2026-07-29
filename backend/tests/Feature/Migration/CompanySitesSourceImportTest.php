<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Country;
use App\Models\MigrationRun;
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

it('creates a company site with its company, profile, contacts and address', function () {
    seedMigrationsConfig();

    $company = Company::factory()->create(['old_id' => 10]);
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->for($country)->create(['name' => 'Lazio']);
    $city = City::factory()->forState($state)->create(['name' => 'Rome']);

    Http::fake([
        fakeMigrationsBaseUrl().'/company-sites*' => Http::response([
            'items' => [[
                'id' => 20,
                'company_id' => 10,
                'name' => 'Rome office',
                'notes' => 'Headquarters',
                'fiscal_code' => 'RSSMRA80A01H501U',
                'vat_number' => '12345678901',
                'sdi_code' => 'ABC1234',
                'email' => 'rome@example.test',
                'phone' => '+39 06 1234567',
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
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'company-sites']);

    runMigrationJobFor($run);

    $site = CompanySite::query()->where('old_id', 20)->firstOrFail();
    $profile = $site->personalData()->firstOrFail();
    $address = $profile->addresses()->firstOrFail();

    expect($site->name)->toBe('Rome office')
        ->and($site->company_id)->toBe($company->id)
        ->and($site->notes)->toBe('Headquarters')
        ->and($profile->company_name)->toBe('Rome office')
        ->and($profile->tax_code)->toBe('RSSMRA80A01H501U')
        ->and($profile->vat_number)->toBe('12345678901')
        ->and($profile->sdi_code)->toBe('ABC1234')
        ->and($profile->contacts()->count())->toBe(2)
        ->and($address->line1)->toBe('Via Roma 1')
        ->and($address->country_id)->toBe($country->id)
        ->and($address->state_id)->toBe($state->id)
        ->and($address->city_id)->toBe($city->id);

    expect($run->fresh()->status)->toBe(MigrationStatus::Completed)
        ->and($run->fresh()->created_rows)->toBe(1)
        ->and($run->fresh()->report)->toBeNull();
});

it('creates the site with a warning when its company has not been migrated', function () {
    seedMigrationsConfig();

    Http::fake([
        fakeMigrationsBaseUrl().'/company-sites*' => Http::response([
            'items' => [[
                'id' => 21,
                'company_id' => 999,
                'name' => 'Unlinked office',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'company-sites']);

    runMigrationJobFor($run);

    expect(CompanySite::query()->where('old_id', 21)->value('company_id'))->toBeNull()
        ->and($run->fresh()->created_rows)->toBe(1)
        ->and($run->fresh()->report[0]['level'])->toBe('warning')
        ->and($run->fresh()->report[0]['message'])->toContain('company_id');
});

it('isolates a missing name and re-imports an existing site idempotently', function () {
    seedMigrationsConfig();

    Http::fake([
        fakeMigrationsBaseUrl().'/company-sites*' => Http::response([
            'items' => [
                ['id' => 22, 'name' => ''],
                ['id' => 23, 'name' => 'Valid office'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $firstRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'company-sites']);
    runMigrationJobFor($firstRun);

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'company-sites']);
    runMigrationJobFor($secondRun);

    expect(CompanySite::query()->where('old_id', 22)->exists())->toBeFalse()
        ->and(CompanySite::query()->where('old_id', 23)->count())->toBe(1)
        ->and($firstRun->fresh()->created_rows)->toBe(1)
        ->and($firstRun->fresh()->failed_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->failed_rows)->toBe(1);
});
