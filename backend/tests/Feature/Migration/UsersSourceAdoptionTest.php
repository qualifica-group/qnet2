<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/**
 * UsersSource adopts a user already in qnet (seeded by hand, no `old_id`)
 * instead of creating a duplicate: by email, else by first + last name among
 * the unlinked users. Only `old_id` and the empty employment references are
 * written; a conflict fails the row (user decision 2026-10-12).
 */
uses(RefreshDatabase::class);

beforeEach(fn () => seedMigrationsConfig());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyUserRecord(array $overrides = []): array
{
    return [
        'id' => 501,
        'email' => 'mario.rossi@legacy.test',
        'password' => Hash::make('legacy-secret'),
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'is_active' => true,
        ...$overrides,
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyUsersImport(array $records): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records), 'offset' => 0, 'limit' => 50, 'total_pages' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'users']);
    runMigrationJobFor($run);

    return $run->fresh();
}

function seededUser(string $email, string $firstName, string $lastName, ?int $oldId = null): User
{
    $user = User::factory()->create(['email' => $email, 'old_id' => $oldId]);
    $user->personalData()->updateOrCreate([], [
        'type' => PersonalDataTypeEnum::Individual,
        'first_name' => $firstName,
        'last_name' => $lastName,
    ]);

    return $user;
}

it('adopts the user with the same email, whatever its case, keeping password and roles', function () {
    $seeded = seededUser('mario.rossi@legacy.test', 'Mario', 'Rossi');
    $seeded->assignRole(Role::factory()->create(['name' => 'Operatore']));
    $password = $seeded->password;
    $users = User::count();

    $run = runLegacyUsersImport([legacyUserRecord(['email' => 'Mario.Rossi@LEGACY.test'])]);

    $seeded->refresh();
    expect($run->created_rows)->toBe(1)
        ->and($run->failed_rows)->toBe(0)
        ->and(User::count())->toBe($users + 1) // only the run's actor
        ->and($seeded->old_id)->toBe(501)
        ->and($seeded->password)->toBe($password)
        ->and($seeded->hasRole('Operatore'))->toBeTrue()
        ->and(collect($run->report)->pluck('message')->implode(' '))->toContain('linked to this legacy record');
});

it('adopts by first and last name when the email differs, keeping the qnet email', function () {
    $seeded = seededUser('m.rossi@qualifica.test', 'MARIO', 'rossi');

    $run = runLegacyUsersImport([legacyUserRecord()]);

    expect($run->created_rows)->toBe(1)
        ->and($seeded->fresh()->old_id)->toBe(501)
        ->and($seeded->fresh()->email)->toBe('m.rossi@qualifica.test')
        ->and(User::query()->where('email', 'mario.rossi@legacy.test')->exists())->toBeFalse();
});

it('fills only the empty employment references of the adopted user', function () {
    $company = Company::factory()->create(['old_id' => 920]);
    $empty = seededUser('mario.rossi@legacy.test', 'Mario', 'Rossi');
    EmploymentProfile::factory()->create(['user_id' => $empty->id, 'company_id' => null]);
    $curated = seededUser('anna.bianchi@legacy.test', 'Anna', 'Bianchi');
    $curatedCompany = Company::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $curated->id, 'company_id' => $curatedCompany->id]);

    runLegacyUsersImport([
        legacyUserRecord(['company_id' => 920, 'relationship_type' => 'employee']),
        legacyUserRecord(['id' => 502, 'email' => 'anna.bianchi@legacy.test', 'first_name' => 'Anna', 'last_name' => 'Bianchi', 'company_id' => 920, 'relationship_type' => 'employee']),
    ]);

    expect($empty->fresh()->employment->company_id)->toBe($company->id)
        ->and($curated->fresh()->employment->company_id)->toBe($curatedCompany->id);
});

it('fails the row when the email belongs to a user linked to another legacy id', function () {
    seededUser('mario.rossi@legacy.test', 'Mario', 'Rossi', oldId: 777);

    $run = runLegacyUsersImport([legacyUserRecord()]);

    expect($run->failed_rows)->toBe(1)
        ->and(collect($run->report)->pluck('message')->implode(' '))->toContain('already belongs to the user linked to legacy id 777');
});

it('fails the row when several unlinked users share the name, naming them', function () {
    seededUser('mario.rossi1@qualifica.test', 'Mario', 'Rossi');
    seededUser('mario.rossi2@qualifica.test', 'Mario', 'Rossi');

    $run = runLegacyUsersImport([legacyUserRecord()]);

    expect($run->failed_rows)->toBe(1)
        ->and(collect($run->report)->pluck('message')->implode(' '))
        ->toContain('mario.rossi1@qualifica.test')
        ->toContain('mario.rossi2@qualifica.test');
});

it('creates the user when the only namesake is already linked to another legacy id', function () {
    seededUser('other.mario@qualifica.test', 'Mario', 'Rossi', oldId: 777);

    $run = runLegacyUsersImport([legacyUserRecord()]);

    expect($run->created_rows)->toBe(1)
        ->and(User::query()->where('email', 'mario.rossi@legacy.test')->value('old_id'))->toBe(501);
});
