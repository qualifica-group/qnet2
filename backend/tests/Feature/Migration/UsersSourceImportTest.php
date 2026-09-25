<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Migrations\MigrationRegistry;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\Country;
use App\Models\MigrationRun;
use App\Models\OperationalSite;
use App\Models\Role;
use App\Models\State;
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

if (! function_exists('fakeBcryptHash')) {
    /**
     * A real bcrypt hash (the external system's own convention), never a
     * plaintext — matches UsersSource::BCRYPT_PATTERN.
     */
    function fakeBcryptHash(string $seed): string
    {
        return Hash::make($seed);
    }
}

// ---------------------------------------------------------------------------
// AC-008 — UsersSource: full profile (card, address, contacts, password,
// employment), old_id, idempotent re-import
// ---------------------------------------------------------------------------

it('creates a user with card, primary address, contacts, verbatim password hash and employment', function () {
    seedMigrationsConfig();
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->for($country)->create(['name' => 'Lazio']);
    $manager = User::factory()->create(['old_id' => 900]);
    $company = Company::factory()->create(['old_id' => 920]);
    $operationalSite = OperationalSite::factory()->create(['old_id' => 930]);
    $role = Role::factory()->create(['name' => 'Admin', 'old_id' => 68]);
    $externalHash = fakeBcryptHash('external-secret');

    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [[
                'id' => 101,
                'email' => 'ada@example.test',
                'password' => $externalHash,
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'tax_code' => 'LVLADA00A00H501Z',
                'vat_number' => 'IT12345678901',
                'birth_date' => '1990-01-01',
                'country' => 'Italy',
                'region' => 'Lazio',
                'city' => 'Rome',
                'street' => 'Via Roma 1',
                'postal_code' => '00100',
                'personal_email' => 'ada.private@example.test',
                'business_phone' => '+39 06 1234567',
                'personal_phone' => '+39 333 1234567',
                'is_active' => true,
                'is_manager' => false,
                'job_description' => 'Engineer',
                'reports_to_id' => 900,
                'business_function_id' => 910,
                'relationship_type' => 'employee',
                'company_id' => 920,
                'operational_site_id' => 930,
                'qualification_type' => 'coordinator',
                'hired_at' => '2020-01-01',
                'terminated_at' => null,
                'standard_daily_minutes' => 480,
                'break_daily_minutes' => 30,
                'roles' => [['id' => 68, 'name' => 'Admin']],
            ]],
            'pagination' => ['total' => 1, 'offset' => 0, 'limit' => 50, 'total_pages' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);

    runMigrationJobFor($run);

    $ada = User::query()->where('email', 'ada@example.test')->first();

    expect($ada)->not->toBeNull()
        ->and($ada->old_id)->toBe(101)
        ->and($ada->password)->toBe($externalHash) // verbatim, NOT re-hashed
        ->and($ada->personalData?->first_name)->toBe('Ada')
        ->and($ada->personalData?->last_name)->toBe('Lovelace')
        ->and($ada->personalData?->tax_code)->toBe('LVLADA00A00H501Z')
        ->and($ada->is_active)->toBeTrue()
        ->and($ada->hasRole('Admin'))->toBeTrue();

    $address = $ada->personalData->addresses()->first();
    expect($address)->not->toBeNull()
        ->and($address->is_primary)->toBeTrue()
        ->and($address->line1)->toBe('Via Roma 1')
        ->and($address->country_id)->toBe($country->id)
        ->and($address->state_id)->toBe($state->id);

    $contacts = $ada->personalData->contacts()->orderBy('type')->get();
    expect($contacts)->toHaveCount(3);

    $email = $contacts->firstWhere('type', 'email');
    expect($email->value)->toBe('ada.private@example.test')
        ->and($email->label)->toBe('Personale')
        ->and($email->is_primary)->toBeTrue();

    $phones = $contacts->where('type', 'phone');
    expect($phones->pluck('label')->sort()->values()->all())->toBe(['Aziendale', 'Personale']);

    $employment = $ada->employment;
    expect($employment)->not->toBeNull()
        ->and($employment->reportsToIds)->toBe([$manager->id])
        ->and($employment->company_id)->toBe($company->id)
        ->and($employment->primary_operational_site_id)->toBe($operationalSite->id)
        ->and($employment->remote_operational_site_ids)->toBe([])
        ->and($employment->relationship_type->value)->toBe('employee')
        ->and($employment->qualification_type->value)->toBe('coordinator')
        ->and($employment->standard_daily_minutes)->toBe(480);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->skipped_rows)->toBe(0);

    // Re-import the SAME external user: idempotent, no duplicate user row nor
    // duplicate physical-site pivot row (AC-031).
    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    runMigrationJobFor($secondRun);

    expect(User::query()->where('email', 'ada@example.test')->count())->toBe(1);

    $secondFresh = $secondRun->fresh();
    expect($secondFresh->created_rows)->toBe(0)
        ->and($secondFresh->skipped_rows)->toBe(1);

    $employment->refresh();
    expect($employment->operationalSites()->wherePivot('is_primary', true)->count())->toBe(1)
        ->and($employment->primary_operational_site_id)->toBe($operationalSite->id);
});

it('honors an inactive external user (is_active=false) instead of forcing active', function () {
    seedMigrationsConfig();

    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [[
                'id' => 150,
                'email' => 'inactive@example.test',
                'password' => fakeBcryptHash('inactive-secret'),
                'first_name' => 'In',
                'last_name' => 'Active',
                'is_active' => false,
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);

    runMigrationJobFor($run);

    $user = User::query()->where('email', 'inactive@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_active)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Self-healing re-import backfill (parents/manager migrated late, out-of-order
// same-run relink, manual assignment survives re-import) moved to
// UsersSourceEmploymentBackfillTest.php (spec 0166 MT-B5, keeps this file
// under the file-size budget).
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// AC-009 — remap roles/employment relations via old_id + warning on unresolved
// ---------------------------------------------------------------------------

it('warns (non-fatally) on unresolved role and employment references', function () {
    seedMigrationsConfig();
    $migratedRole = Role::factory()->create(['name' => 'operator', 'old_id' => 55]);

    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [[
                'id' => 201,
                'email' => 'grace@example.test',
                'password' => fakeBcryptHash('grace-secret'),
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'roles' => [['id' => 55, 'name' => 'operator'], ['id' => 999, 'name' => 'ghost']],
                'reports_to_id' => 777,
                'operational_site_id' => 888,
                'relationship_type' => 'not-a-real-type',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);

    runMigrationJobFor($run);

    $grace = User::query()->where('email', 'grace@example.test')->first();

    expect($grace)->not->toBeNull()
        ->and($grace->hasRole($migratedRole->name))->toBeTrue()
        ->and($grace->employment?->reportsToIds)->toBe([])
        ->and($grace->employment?->relationship_type)->toBeNull()
        ->and($grace->employment?->primary_operational_site_id)->toBeNull();

    $fresh = $run->fresh();
    $messages = collect($fresh->report)->pluck('message')->implode(' | ');

    // An unresolvable site is a non-fatal warning: the row is still created.
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($messages)->toContain('999')
        ->and($messages)->toContain('777')
        ->and($messages)->toContain('888')
        ->and($messages)->toContain('not-a-real-type');
});

// ---------------------------------------------------------------------------
// Password — the external hash must already be bcrypt (never re-hashed,
// never accepted as plaintext)
// ---------------------------------------------------------------------------

it('rejects a row whose password is not a valid bcrypt hash', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [[
                'id' => 301,
                'email' => 'plain@example.test',
                'password' => 'plaintext-not-a-hash',
                'first_name' => 'Plain',
                'last_name' => 'Text',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);

    runMigrationJobFor($run);

    expect(User::query()->where('email', 'plain@example.test')->exists())->toBeFalse();

    $fresh = $run->fresh();
    expect($fresh->failed_rows)->toBe(1)
        ->and($fresh->report[0]['level'])->toBe('error')
        ->and($fresh->report[0]['message'])->toContain('bcrypt');
});

// ---------------------------------------------------------------------------
// AC-021 (spec 0111 D-6) — `business_function_id` left the users source: a user
// competence is a function+category pair, which a single external id cannot
// form, so the field is no longer importable and an external record still
// carrying it is imported ignoring it
// ---------------------------------------------------------------------------

it('no longer exposes business_function_id in the users column catalogue', function () {
    $columns = collect(app(MigrationRegistry::class)->resolve('users')->columns())->pluck('id');

    expect($columns)->not->toContain('business_function_id')
        ->and($columns)->toContain('reports_to_id', 'company_id', 'operational_site_id');
});

it('imports a record still carrying business_function_id without error, ignoring the field', function () {
    seedMigrationsConfig();
    BusinessFunction::factory()->create(['old_id' => 910]);

    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [[
                'id' => 705,
                'email' => 'rita@example.test',
                'password' => fakeBcryptHash('rita-secret'),
                'first_name' => 'Rita',
                'last_name' => 'Levi',
                'business_function_id' => 910,
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);

    runMigrationJobFor($run);

    $rita = User::query()->where('email', 'rita@example.test')->first();

    // The field is the record's ONLY employment marker: ignoring it leaves no
    // employment payload at all, hence no profile row.
    expect($rita)->not->toBeNull()
        ->and($rita->old_id)->toBe(705)
        ->and($rita->employment)->toBeNull();

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(0)
        ->and(collect($fresh->report ?? [])->pluck('message')->implode(' | '))->not->toContain('business_function');
});
