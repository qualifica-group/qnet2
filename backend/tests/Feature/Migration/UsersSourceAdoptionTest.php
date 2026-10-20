<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\Company;
use App\Models\EmploymentContract;
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
    // 0272 D-2: the company copy comes from the contract history, so the curated value is a condition.
    $curatedProfile = EmploymentProfile::factory()->create(['user_id' => $curated->id, 'company_id' => $curatedCompany->id]);
    EmploymentContract::factory()->create(['employment_profile_id' => $curatedProfile->id, 'company_id' => $curatedCompany->id]);

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

    $created = User::query()->where('email', 'mario.rossi@legacy.test')->first();
    expect($run->created_rows)->toBe(1)
        ->and($created->old_id)->toBe(501)
        ->and($created->personalData->first_name)->toBe('[OLD] Mario')
        ->and($created->name)->toBe('[OLD] Mario Rossi');
});

it('leaves the name of an inactive legacy user that matched nobody unmarked', function () {
    $run = runLegacyUsersImport([legacyUserRecord(['is_active' => false])]);

    $created = User::query()->where('email', 'mario.rossi@legacy.test')->first();
    expect($run->created_rows)->toBe(1)
        ->and($created->personalData->first_name)->toBe('Mario')
        ->and($created->name)->toBe('Mario Rossi');
});

it('never marks the name of an adopted user', function () {
    $seeded = seededUser('m.rossi@qualifica.test', 'Mario', 'Rossi');

    runLegacyUsersImport([legacyUserRecord()]);

    expect($seeded->fresh()->personalData->first_name)->toBe('Mario');
});

// ---------------------------------------------------------------------------
// Spec 0285 — link-only mode: a matched user gets only `old_id`, an unmatched
// one is created as in the full import
// ---------------------------------------------------------------------------

it('in link-only mode writes only the old_id of the user matched by email', function () {
    config(['migrations.users_link_only' => true]);
    $seeded = seededUser('mario.rossi@legacy.test', 'Mario', 'Rossi');
    $seeded->assignRole(Role::factory()->create(['name' => 'Operatore']));
    EmploymentProfile::factory()->create(['user_id' => $seeded->id, 'company_id' => null]);
    Company::factory()->create(['old_id' => 920]);
    $password = $seeded->password;
    $users = User::count();

    $run = runLegacyUsersImport([legacyUserRecord(['is_active' => false, 'company_id' => 920, 'relationship_type' => 'employee'])]);

    $seeded->refresh();
    expect($run->created_rows)->toBe(1)
        ->and(User::count())->toBe($users + 1) // only the run's actor
        ->and($seeded->old_id)->toBe(501)
        ->and($seeded->is_active)->toBeTrue()
        ->and($seeded->password)->toBe($password)
        ->and($seeded->hasRole('Operatore'))->toBeTrue()
        ->and($seeded->personalData->first_name)->toBe('Mario')
        ->and($seeded->employment->company_id)->toBeNull()
        ->and(collect($run->report)->pluck('message')->implode(' '))->toContain('(link-only mode)');
});

it('in link-only mode links by first and last name keeping the qnet email', function () {
    config(['migrations.users_link_only' => true]);
    $seeded = seededUser('m.rossi@qualifica.test', 'Mario', 'Rossi');

    runLegacyUsersImport([legacyUserRecord()]);

    expect($seeded->fresh()->old_id)->toBe(501)
        ->and($seeded->fresh()->email)->toBe('m.rossi@qualifica.test');
});

// REQUIREMENT CHANGED (spec 0285 D-3/D-6, user decision 2026-10-20): an
// unmatched legacy user is created from the import instead of being skipped,
// always disabled, even when it is active in the legacy.
it('in link-only mode creates a legacy user that matches nobody as the full import does, disabled', function () {
    config(['migrations.users_link_only' => true]);
    $company = Company::factory()->create(['old_id' => 920]);

    $run = runLegacyUsersImport([legacyUserRecord(['company_id' => 920, 'relationship_type' => 'employee'])]);

    $created = User::query()->where('email', 'mario.rossi@legacy.test')->first();
    expect($run->created_rows)->toBe(1)
        ->and($run->skipped_rows)->toBe(0)
        ->and($created->old_id)->toBe(501)
        ->and($created->is_active)->toBeFalse()
        ->and(Hash::check('legacy-secret', $created->password))->toBeTrue()
        ->and($created->personalData->first_name)->toBe('[OLD] Mario')
        ->and($created->employment->company_id)->toBe($company->id)
        ->and(collect($run->report)->pluck('message')->implode(' '))
        ->toContain('No qnet user matches mario.rossi@legacy.test: created disabled from the legacy record (link-only mode).');
});

it('creates an unmatched active legacy user active in the full import', function () {
    $run = runLegacyUsersImport([legacyUserRecord()]);

    expect($run->created_rows)->toBe(1)
        ->and(User::query()->where('old_id', 501)->first()->is_active)->toBeTrue();
});

it('in link-only mode relinks the manager of a created user, never the employment of a matched one', function () {
    config(['migrations.users_link_only' => true]);
    Company::factory()->create(['old_id' => 920]);
    $matched = seededUser('anna.bianchi@legacy.test', 'Anna', 'Bianchi');
    EmploymentProfile::factory()->create(['user_id' => $matched->id, 'company_id' => null]);

    // The created user (501) reports to 600, created later in the same run.
    runLegacyUsersImport([
        legacyUserRecord(['reports_to_id' => 600]),
        legacyUserRecord(['id' => 502, 'email' => 'anna.bianchi@legacy.test', 'first_name' => 'Anna', 'last_name' => 'Bianchi', 'company_id' => 920, 'relationship_type' => 'employee']),
        legacyUserRecord(['id' => 600, 'email' => 'luca.verdi@legacy.test', 'first_name' => 'Luca', 'last_name' => 'Verdi']),
    ]);

    $manager = User::query()->where('old_id', 600)->first();
    expect(User::query()->where('old_id', 501)->first()->employment->reportsToIds)->toBe([$manager->id])
        ->and($matched->fresh()->old_id)->toBe(502)
        ->and($matched->fresh()->employment->company_id)->toBeNull();
});

it('in link-only mode does not fill the empty employment of an already linked user on re-import', function () {
    config(['migrations.users_link_only' => true]);
    Company::factory()->create(['old_id' => 920]);
    $linked = seededUser('mario.rossi@legacy.test', 'Mario', 'Rossi', oldId: 501);
    EmploymentProfile::factory()->create(['user_id' => $linked->id, 'company_id' => null]);

    $run = runLegacyUsersImport([legacyUserRecord(['company_id' => 920, 'relationship_type' => 'employee'])]);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->report)->toBeEmpty()
        ->and($linked->fresh()->employment->company_id)->toBeNull();
});

it('in link-only mode still fails the row on a matcher conflict', function () {
    config(['migrations.users_link_only' => true]);
    seededUser('mario.rossi@legacy.test', 'Mario', 'Rossi', oldId: 777);

    $run = runLegacyUsersImport([legacyUserRecord()]);

    expect($run->failed_rows)->toBe(1)
        ->and(collect($run->report)->pluck('message')->implode(' '))->toContain('already belongs to the user linked to legacy id 777');
});
