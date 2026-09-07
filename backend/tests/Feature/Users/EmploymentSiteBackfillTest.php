<?php

use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Spec 0103 (M2) AC-001/AC-002/AC-003 — backfill + column drop migration
|--------------------------------------------------------------------------
|
| Drives `2026_09_07_100100_move_employment_operational_site_to_pivot`
| directly (down()/up()), the same precedent as NoteMigrationRollbackTest:
| independent of migration order or of whatever else the suite migrates, and
| no Eloquent on `employment_profiles`/the pivot — the migration itself is
| query-builder-only and the Model/relation layer for the pivot doesn't exist
| yet (later microtask). Each test starts and ends with `migrate:fresh` so it
| leaves no trace for the rest of the suite (RefreshDatabase is NOT used
| here: dropping an FK-backed column forces SQLite to rebuild the table via a
| `PRAGMA foreign_keys` toggle, which RefreshDatabase's per-test transaction
| turns into a no-op — same guard QuoteWorkflowMigrationTest documents for
| its own `->change()` migration).
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

function moveEmploymentOperationalSiteMigration(): object
{
    return require database_path('migrations/2026_09_07_100100_move_employment_operational_site_to_pivot.php');
}

/**
 * Row is inserted via the query builder, never Eloquent: after the
 * migration's up() the `operational_site_id` column no longer exists, so any
 * test that seeds a PRE-migration profile (via down()) needs a shape that
 * still works once the column comes back, without depending on the
 * EmploymentProfile model's $fillable (out of this microtask's scope).
 */
function createEmploymentProfileRow(?int $operationalSiteId): int
{
    return DB::table('employment_profiles')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'is_manager' => false,
        'operational_site_id' => $operationalSiteId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('AC-001: backfills the physical site as the single is_primary pivot row and drops the column', function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');

    $migration = moveEmploymentOperationalSiteMigration();
    $migration->down(); // rewind: bring the column back to seed a pre-migration profile

    $site = OperationalSite::factory()->create();
    $profileId = createEmploymentProfileRow($site->id);

    $migration->up();

    expect(Schema::hasColumn('employment_profiles', 'operational_site_id'))->toBeFalse();

    $rows = DB::table('employment_profile_operational_site')
        ->where('employment_profile_id', $profileId)
        ->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->operational_site_id)->toBe($site->id)
        ->and((bool) $rows->first()->is_primary)->toBeTrue();

    Artisan::call('migrate:fresh');
});

it('AC-002: a profile with no operational site produces no pivot row', function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');

    $migration = moveEmploymentOperationalSiteMigration();
    $migration->down();

    $profileId = createEmploymentProfileRow(null);

    $migration->up();

    expect(Schema::hasColumn('employment_profiles', 'operational_site_id'))->toBeFalse();
    expect(DB::table('employment_profile_operational_site')->where('employment_profile_id', $profileId)->exists())
        ->toBeFalse();

    Artisan::call('migrate:fresh');
});

it('AC-003: down() restores the column and repopulates it from the primary pivot row', function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');

    $migration = moveEmploymentOperationalSiteMigration();
    $migration->down();

    $site = OperationalSite::factory()->create();
    $profileId = createEmploymentProfileRow($site->id);

    $migration->up();
    $migration->down();

    expect(Schema::hasColumn('employment_profiles', 'operational_site_id'))->toBeTrue();
    expect(DB::table('employment_profiles')->where('id', $profileId)->value('operational_site_id'))
        ->toBe($site->id);

    Artisan::call('migrate:fresh');
});
