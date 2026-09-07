<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
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
    $businessFunction = BusinessFunction::factory()->create(['old_id' => 910]);
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
        ->and($employment->reports_to_id)->toBe($manager->id)
        ->and($employment->business_function_id)->toBe($businessFunction->id)
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

it('back-fills employment relations on re-import once the parents are migrated (self-healing skip)', function () {
    seedMigrationsConfig();

    // Round 1: the user is imported BEFORE any parent exists -> relations null.
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [[
                'id' => 449,
                'email' => 'nicola@example.test',
                'password' => fakeBcryptHash('nicola-secret'),
                'first_name' => 'Nicola',
                'last_name' => 'Eliseo',
                'job_description' => 'Senior Full Stack Engineer',
                'is_manager' => false,
                'reports_to_id' => 445,
                'business_function_id' => 6,
                'operational_site_id' => 15,
                'standard_daily_minutes' => 480,
                'break_daily_minutes' => 30,
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']));

    $user = User::query()->where('email', 'nicola@example.test')->first();
    expect($user->employment)->not->toBeNull()
        ->and($user->employment->reports_to_id)->toBeNull()
        ->and($user->employment->business_function_id)->toBeNull()
        ->and($user->employment->primary_operational_site_id)->toBeNull();

    // The parents are migrated afterwards (their own sources set old_id).
    $manager = User::factory()->create(['old_id' => 445]);
    $businessFunction = BusinessFunction::factory()->create(['old_id' => 6]);
    $operationalSite = OperationalSite::factory()->create(['old_id' => 15]);

    // Round 2: re-import the SAME user -> skipped, but relations back-filled.
    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    runMigrationJobFor($secondRun);

    $employment = $user->employment()->with('operationalSites')->first();
    expect($employment->reports_to_id)->toBe($manager->id)
        ->and($employment->business_function_id)->toBe($businessFunction->id)
        ->and($employment->primary_operational_site_id)->toBe($operationalSite->id)
        ->and($employment->operationalSites()->wherePivot('is_primary', true)->count())->toBe(1);

    $fresh = $secondRun->fresh();
    expect($fresh->created_rows)->toBe(0)
        ->and($fresh->skipped_rows)->toBe(1)
        ->and(collect($fresh->report)->pluck('message')->implode(' | '))->toContain('Relinked');

    // Idempotent once linked: a third run back-fills nothing.
    $thirdRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    runMigrationJobFor($thirdRun);
    expect(collect($thirdRun->fresh()->report ?? [])->pluck('message')->implode(' | '))->not->toContain('Relinked');
});

// ---------------------------------------------------------------------------
// AC-031 — self-healing site backfill is idempotent and never overwrites a
// physical site assigned by hand
// ---------------------------------------------------------------------------

it('does not overwrite a manually-assigned physical site on re-import self-healing', function () {
    seedMigrationsConfig();

    // Round 1: the external site is not migrated yet -> no physical site,
    // non-fatal warning, import proceeds.
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [[
                'id' => 601,
                'email' => 'grazia@example.test',
                'password' => fakeBcryptHash('grazia-secret'),
                'first_name' => 'Grazia',
                'last_name' => 'Deledda',
                'operational_site_id' => 70,
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']));

    $user = User::query()->where('email', 'grazia@example.test')->first();
    expect($user->employment->primary_operational_site_id)->toBeNull();

    // An administrator manually assigns a physical site by hand, out of band.
    $manualSite = OperationalSite::factory()->create();
    $user->employment->operationalSites()->attach($manualSite->id, ['is_primary' => true]);

    // The external site is migrated afterwards, under the SAME external id.
    $externalSite = OperationalSite::factory()->create(['old_id' => 70]);

    // Round 2: re-import the SAME user -> skipped; self-healing does NOT
    // touch a physical site that already exists (the manual one survives).
    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    runMigrationJobFor($secondRun);

    $employment = $user->employment()->with('operationalSites')->first();
    expect($employment->primary_operational_site_id)->toBe($manualSite->id)
        ->and($employment->primary_operational_site_id)->not->toBe($externalSite->id)
        ->and($employment->operationalSites()->wherePivot('is_primary', true)->count())->toBe(1)
        ->and($employment->operationalSites()->count())->toBe(1);

    expect(collect($secondRun->fresh()->report ?? [])->pluck('message')->implode(' | '))->not->toContain('Relinked');
});

it('relinks reports_to_id in a SINGLE run when the manager is imported after the subordinate', function () {
    seedMigrationsConfig();

    // The subordinate (id 3) references a manager (id 500) that appears LATER
    // in the SAME page -> null on the first pass, back-filled by the second.
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [
                [
                    'id' => 3,
                    'email' => 'subordinate@example.test',
                    'password' => fakeBcryptHash('sub-secret'),
                    'first_name' => 'Sub',
                    'last_name' => 'Ordinate',
                    'is_manager' => false,
                    'reports_to_id' => 500,
                ],
                [
                    'id' => 500,
                    'email' => 'manager@example.test',
                    'password' => fakeBcryptHash('mgr-secret'),
                    'first_name' => 'Man',
                    'last_name' => 'Ager',
                    'is_manager' => true,
                    'job_description' => 'Head of Engineering',
                ],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);

    runMigrationJobFor($run);

    $manager = User::query()->where('email', 'manager@example.test')->first();
    $subordinate = User::query()->where('email', 'subordinate@example.test')->first();

    expect($subordinate->employment->reports_to_id)->toBe($manager->id);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->skipped_rows)->toBe(0)
        ->and(collect($fresh->report)->pluck('message')->implode(' | '))->toContain('Relinked 1 employment reference(s) after import');
});

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
        ->and($grace->employment?->reports_to_id)->toBeNull()
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
