<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\OperationalSite;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Self-healing re-import backfill for UsersSource employment relations (spec
 * 0013 idempotency, extended by spec 0103 for the site pivot and spec 0166
 * for the reports-to pivot): split out of UsersSourceImportTest.php to keep
 * that file under the file-size budget (backend.md/engineering.md §6).
 */
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
// AC-016 — import legacy: reports-to backfill on re-import once the manager
// is migrated (attached when the profile has none), unresolved before that
// ---------------------------------------------------------------------------

it('back-fills employment relations on re-import once the parents are migrated (self-healing skip)', function () {
    seedMigrationsConfig();

    // Round 1: the user is imported BEFORE any parent exists -> relations unresolved.
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
        ->and($user->employment->reportsToIds)->toBe([])
        ->and($user->employment->primary_operational_site_id)->toBeNull();

    // The parents are migrated afterwards (their own sources set old_id).
    $manager = User::factory()->create(['old_id' => 445]);
    $operationalSite = OperationalSite::factory()->create(['old_id' => 15]);

    // Round 2: re-import the SAME user -> skipped, but relations back-filled:
    // the profile had NO manager at all, so the resolved one is attached
    // (AC-016).
    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    runMigrationJobFor($secondRun);

    $employment = $user->employment()->with(['operationalSites', 'reportsTo'])->first();
    expect($employment->reportsToIds)->toBe([$manager->id])
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

// ---------------------------------------------------------------------------
// AC-016 — re-import on a profile that already has a manager leaves the
// pivot untouched (a manual assignment survives re-import, just like the
// site membership above)
// ---------------------------------------------------------------------------

it('does not attach a second manager on re-import when the profile already has one', function () {
    seedMigrationsConfig();

    // Round 1: the external manager is not migrated yet -> no manager
    // attached, non-fatal warning, import proceeds.
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'items' => [[
                'id' => 610,
                'email' => 'enrico@example.test',
                'password' => fakeBcryptHash('enrico-secret'),
                'first_name' => 'Enrico',
                'last_name' => 'Fermi',
                'reports_to_id' => 950,
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']));

    $user = User::query()->where('email', 'enrico@example.test')->first();
    expect($user->employment->reportsToIds)->toBe([]);

    // An administrator manually assigns a manager by hand, out of band.
    $manualManager = User::factory()->create();
    $user->employment->reportsTo()->attach($manualManager->id);

    // The external manager is migrated afterwards, under the SAME external id.
    $externalManager = User::factory()->create(['old_id' => 950]);

    // Round 2: re-import the SAME user -> skipped; self-healing does NOT
    // attach a second manager when the profile already has one (the manual
    // assignment survives).
    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    runMigrationJobFor($secondRun);

    $employment = $user->employment()->with('reportsTo')->first();
    expect($employment->reportsToIds)->toBe([$manualManager->id])
        ->and($employment->reportsToIds)->not->toContain($externalManager->id);

    expect(collect($secondRun->fresh()->report ?? [])->pluck('message')->implode(' | '))->not->toContain('Relinked');
});

// ---------------------------------------------------------------------------
// AC-016 — end-of-import relinking pass: the manager appears LATER in the
// same run than its subordinate
// ---------------------------------------------------------------------------

it('relinks the manager in a SINGLE run when it is imported after the subordinate', function () {
    seedMigrationsConfig();

    // The subordinate (id 3) references a manager (id 500) that appears LATER
    // in the SAME page -> unresolved on the first pass, back-filled by the
    // second.
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

    expect($subordinate->employment->reportsToIds)->toBe([$manager->id]);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->skipped_rows)->toBe(0)
        ->and(collect($fresh->report)->pluck('message')->implode(' | '))->toContain('Relinked 1 employment reference(s) after import');
});
