<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\Company;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Spec 0272 D-9 / AC-014: the legacy relationship type and company become the
 * FIRST contract condition (never written straight on the profile), and the
 * legacy qualification level is ignored.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contractLegacyUser(array $overrides = []): array
{
    return [
        'id' => 601,
        'email' => 'carla.verdi@legacy.test',
        'password' => Hash::make('legacy-secret'),
        'first_name' => 'Carla',
        'last_name' => 'Verdi',
        'is_active' => true,
        'qualification_type' => 'coordinator',
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $record
 */
function importContractLegacyUser(array $record): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [$record],
            'pagination' => ['total' => 1, 'offset' => 0, 'limit' => 50, 'total_pages' => 1],
        ]),
    ]);

    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $actor = User::factory()->create();
    $actor->assignRole('super-admin');

    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    (new RunMigrationJob($run->id))->handle(app(MigrationService::class));

    return $run->fresh();
}

beforeEach(function () {
    seedMigrationsConfig();
});

it('0272 AC-014 creates the first condition from the legacy type and company, ignoring qualification_type', function () {
    $company = Company::factory()->create(['old_id' => 920]);

    $run = importContractLegacyUser(contractLegacyUser([
        'relationship_type' => 'self_employed', 'company_id' => 920, 'hired_at' => '2021-03-01',
    ]));

    $profile = User::query()->where('email', 'carla.verdi@legacy.test')->first()->employment;

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->failed_rows)->toBe(0)
        ->and($profile->getAttributes())->not->toHaveKey('qualification_type')
        ->and($profile->contracts)->toHaveCount(1)
        ->and($profile->contracts->first()->starts_on->toDateString())->toBe('2021-03-01')
        ->and($profile->contracts->first()->relationship_type->value)->toBe('self_employed')
        ->and($profile->contracts->first()->company_id)->toBe($company->id)
        ->and($profile->relationship_type->value)->toBe('self_employed')
        ->and($profile->company_id)->toBe($company->id);
});

it('0272 AC-014 a company without a relationship type becomes the other type', function () {
    Company::factory()->create(['old_id' => 920]);

    importContractLegacyUser(contractLegacyUser(['company_id' => 920, 'hired_at' => '2021-03-01']));

    $contract = User::query()->where('email', 'carla.verdi@legacy.test')->first()->employment->contracts->first();

    expect($contract->relationship_type->value)->toBe('other');
});

it('0272 AC-014 starts on the import date when the legacy has no hired_at', function () {
    importContractLegacyUser(contractLegacyUser(['relationship_type' => 'employee']));

    $contract = User::query()->where('email', 'carla.verdi@legacy.test')->first()->employment->contracts->first();

    expect($contract->starts_on->toDateString())->toBe(today()->toDateString());
});

it('0272 AC-014 a second import does not duplicate the condition', function () {
    $record = contractLegacyUser(['relationship_type' => 'employee', 'hired_at' => '2021-03-01']);

    importContractLegacyUser($record);
    importContractLegacyUser($record);

    $profile = User::query()->where('email', 'carla.verdi@legacy.test')->first()->employment;

    expect($profile->contracts)->toHaveCount(1);
});

it('0272 AC-014 a record with neither type nor company creates no condition', function () {
    importContractLegacyUser(contractLegacyUser(['hired_at' => '2021-03-01']));

    $profile = User::query()->where('email', 'carla.verdi@legacy.test')->first()->employment;

    expect($profile->contracts)->toHaveCount(0);
});
