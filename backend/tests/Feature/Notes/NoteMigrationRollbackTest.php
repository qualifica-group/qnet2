<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| AC-020 — down()/up() reversibility
|--------------------------------------------------------------------------
|
| Not using RefreshDatabase: this test drives the two note migration FILES
| directly (down()/up()), independent of migration order or of whatever else
| the suite migrates — mirrors RewardStatusReshapeMigrationTest's own
| precedent. Ends with migrate:fresh so it leaves no trace for the rest of
| the suite.
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

it('down() drops both tables in FK-safe order, up() restores them (AC-020)', function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');

    $noteMentionsMigration = require database_path('migrations/2026_07_22_150200_create_note_mentions_table.php');
    $notesMigration = require database_path('migrations/2026_07_22_150100_create_notes_table.php');

    $noteMentionsMigration->down();
    $notesMigration->down();

    expect(Schema::hasTable('note_mentions'))->toBeFalse();
    expect(Schema::hasTable('notes'))->toBeFalse();

    $notesMigration->up();
    $noteMentionsMigration->up();

    expect(Schema::hasTable('notes'))->toBeTrue();
    expect(Schema::hasTable('note_mentions'))->toBeTrue();

    Artisan::call('migrate:fresh');
});
