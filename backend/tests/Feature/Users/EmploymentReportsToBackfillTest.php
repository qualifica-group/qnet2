<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Spec 0166 (D-2) AC-001 — backfill + column drop migration
|--------------------------------------------------------------------------
|
| Drives `2026_09_25_100100_move_employment_reports_to_to_pivot` directly
| (down()/up()), same precedent as EmploymentSiteBackfillTest.php (spec 0103
| M2): independent of migration order, no Eloquent on `employment_profiles`/
| the pivot. Each test starts and ends with `migrate:fresh` so it leaves no
| trace for the rest of the suite (RefreshDatabase is NOT used here: dropping
| an FK-backed column forces SQLite to rebuild the table via a
| `PRAGMA foreign_keys` toggle, which RefreshDatabase's per-test transaction
| turns into a no-op).
|
| GUARD (non-negotiable): migrate:fresh is destructive and this project has
| no .env.testing, so nothing but phpunit.xml's DB_CONNECTION=sqlite /
| DB_DATABASE=:memory: keeps it off a real database — refuse to run rather
| than risk it.
*/

if (! function_exists('assertSafeToWipeDatabase')) {
    function assertSafeToWipeDatabase(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException(
                'Refusing to run migrate:fresh: the active connection is not an in-memory SQLite '
                .'database ('.$connection->getDriverName().':'.$connection->getDatabaseName().'). '
                .'This guard exists because migrate:fresh against a real database would destroy it.'
            );
        }
    }
}

function moveReportsToPivotMigration(): object
{
    return require database_path('migrations/2026_09_25_100100_move_employment_reports_to_to_pivot.php');
}

/**
 * Row is inserted via the query builder, never Eloquent: after the
 * migration's up() the `reports_to_id` column no longer exists, so any test
 * that seeds a PRE-migration profile (via down()) needs a shape that still
 * works once the column comes back, without depending on the
 * EmploymentProfile model's $fillable (out of this microtask's scope).
 */
function createEmploymentProfileRowWithReportsTo(?int $reportsToId): int
{
    return DB::table('employment_profiles')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'is_manager' => false,
        'reports_to_id' => $reportsToId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('AC-001: backfills the manager as a single pivot row and drops the column', function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');

    $migration = moveReportsToPivotMigration();
    $migration->down(); // rewind: bring the column back to seed a pre-migration profile

    $manager = User::factory()->create();
    $profileId = createEmploymentProfileRowWithReportsTo($manager->id);

    $migration->up();

    expect(Schema::hasColumn('employment_profiles', 'reports_to_id'))->toBeFalse();

    $rows = DB::table('employment_profile_manager')
        ->where('employment_profile_id', $profileId)
        ->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->user_id)->toBe($manager->id);

    Artisan::call('migrate:fresh');
});

it('AC-001: a profile with no reports-to produces no pivot row', function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');

    $migration = moveReportsToPivotMigration();
    $migration->down();

    $profileId = createEmploymentProfileRowWithReportsTo(null);

    $migration->up();

    expect(Schema::hasColumn('employment_profiles', 'reports_to_id'))->toBeFalse();
    expect(DB::table('employment_profile_manager')->where('employment_profile_id', $profileId)->exists())
        ->toBeFalse();

    Artisan::call('migrate:fresh');
});

it('AC-001: down() restores the column and repopulates it from the manager', function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');

    $migration = moveReportsToPivotMigration();
    $migration->down();

    $manager = User::factory()->create();
    $profileId = createEmploymentProfileRowWithReportsTo($manager->id);

    $migration->up();
    $migration->down();

    expect(Schema::hasColumn('employment_profiles', 'reports_to_id'))->toBeTrue();
    expect(DB::table('employment_profiles')->where('id', $profileId)->value('reports_to_id'))
        ->toBe($manager->id);

    Artisan::call('migrate:fresh');
});

it('down() picks the LOWEST pivot id per profile and leaves the other managers in the pivot (documented lossy)', function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');

    $migration = moveReportsToPivotMigration();
    $migration->down();

    $earliest = User::factory()->create();
    $later = User::factory()->create();
    $profileId = createEmploymentProfileRowWithReportsTo($earliest->id);

    $migration->up();

    // A second manager assigned AFTER the migration split the column into
    // the pivot — the real-world scenario this asymmetry documents.
    DB::table('employment_profile_manager')->insert([
        'employment_profile_id' => $profileId,
        'user_id' => $later->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->down();

    expect(DB::table('employment_profiles')->where('id', $profileId)->value('reports_to_id'))
        ->toBe($earliest->id);
    expect(DB::table('employment_profile_manager')->where('employment_profile_id', $profileId)->where('user_id', $earliest->id)->exists())
        ->toBeFalse();
    expect(DB::table('employment_profile_manager')->where('employment_profile_id', $profileId)->where('user_id', $later->id)->exists())
        ->toBeTrue();

    Artisan::call('migrate:fresh');
});
