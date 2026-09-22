<?php

use App\Models\QuoteLine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| AC-001 — down()/up() reversibility of `quote_lines.offer_line_id`
|--------------------------------------------------------------------------
|
| Not using RefreshDatabase: drives the migration FILE directly (down()/up()),
| mirroring NoteMigrationRollbackTest's own precedent, so a real ALTER TABLE
| DROP COLUMN/CONSTRAINED runs outside any per-test transaction wrapper. Ends
| with migrate:fresh so it leaves no trace for the rest of the suite.
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

it('AC-001: existing rows are NULL, and down()/up() are reversible', function () {
    assertSafeToWipeDatabase();
    Artisan::call('migrate:fresh');

    expect(Schema::hasColumn('quote_lines', 'offer_line_id'))->toBeTrue();

    $line = QuoteLine::factory()->create();
    expect($line->offer_line_id)->toBeNull();

    $migration = require database_path('migrations/2026_09_22_100000_add_offer_line_id_to_quote_lines_table.php');
    $migration->down();

    expect(Schema::hasColumn('quote_lines', 'offer_line_id'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('quote_lines', 'offer_line_id'))->toBeTrue();

    Artisan::call('migrate:fresh');
});
