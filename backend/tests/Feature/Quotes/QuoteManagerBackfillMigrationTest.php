<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0087, D-12/T-02: the best-effort DATA backfill for `quote_user`/
 * `quotes.operator_id`. `RefreshDatabase` already migrates a clean, EMPTY
 * schema before every test — so the migration under test is invoked
 * directly (`require database_path(...)`), the same technique
 * QuoteWorkflowMigrationTest/SectorTest use, AFTER seeding rows that stand
 * in for pre-existing data.
 */
uses(RefreshDatabase::class);

if (! function_exists('backfillMigration')) {
    function backfillMigration(): object
    {
        return require database_path('migrations/2026_08_31_120000_backfill_quote_managers_from_opportunity.php');
    }
}

it('copies the opportunity\'s GA onto the quote at the same positions when supervisor_id is null (AC-015)', function () {
    $opportunity = Opportunity::factory()->create();
    $ga1 = User::factory()->create();
    $ga3 = User::factory()->create();
    $opportunity->managers()->sync([$ga1->id => ['position' => 1], $ga3->id => ['position' => 3]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => null]);

    backfillMigration()->up();

    $rows = DB::table('quote_user')->where('quote_id', $quote->id)->orderBy('position')->get();
    expect($rows->pluck('user_id')->all())->toBe([$ga1->id, $ga3->id])
        ->and($rows->pluck('position')->all())->toBe([1, 3])
        ->and(DB::table('quotes')->where('id', $quote->id)->value('operator_id'))->toBeNull();
});

it('the former supervisor becomes the OPERATOR slot (position 2) when supervisor_id is set, without duplicating the pivot unique keys (AC-015)', function () {
    $opportunity = Opportunity::factory()->create();
    $supervisor = User::factory()->create();
    // The supervisor already sits at position 1 on the opportunity — the
    // migration must move them to position 2 on the QUOTE pivot, not leave
    // them duplicated across two rows (unique [quote_id,user_id]).
    $opportunity->managers()->sync([$supervisor->id => ['position' => 1]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $supervisor->id]);

    backfillMigration()->up();

    $rows = DB::table('quote_user')->where('quote_id', $quote->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->user_id)->toBe($supervisor->id)
        ->and($rows->first()->position)->toBe(2)
        ->and(DB::table('quotes')->where('id', $quote->id)->value('operator_id'))->toBe($supervisor->id);
});

it('displaces whoever the opportunity copy already put at position 2, without touching other positions', function () {
    $opportunity = Opportunity::factory()->create();
    $staleOperator = User::factory()->create();
    $ga1 = User::factory()->create();
    $supervisor = User::factory()->create();
    $opportunity->managers()->sync([
        $ga1->id => ['position' => 1],
        $staleOperator->id => ['position' => 2],
    ]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $supervisor->id]);

    backfillMigration()->up();

    $rows = DB::table('quote_user')->where('quote_id', $quote->id)->orderBy('position')->get();
    expect($rows->pluck('user_id')->all())->toBe([$ga1->id, $supervisor->id])
        ->and($rows->pluck('position')->all())->toBe([1, 2]);
});

it('is a no-op on a quote with neither opportunity managers nor a supervisor', function () {
    $quote = Quote::factory()->create();

    backfillMigration()->up();

    expect(DB::table('quote_user')->where('quote_id', $quote->id)->count())->toBe(0)
        ->and(DB::table('quotes')->where('id', $quote->id)->value('operator_id'))->toBeNull();
});

it('down() clears the pivot and nulls operator_id (data-only reversal)', function () {
    $opportunity = Opportunity::factory()->create();
    $supervisor = User::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $supervisor->id]);
    $migration = backfillMigration();
    $migration->up();
    expect(DB::table('quote_user')->count())->toBeGreaterThan(0);

    $migration->down();

    expect(DB::table('quote_user')->count())->toBe(0)
        ->and(DB::table('quotes')->where('id', $quote->id)->value('operator_id'))->toBeNull()
        // The schema itself is untouched: dropping the column is T-01's own migration's job.
        ->and(Schema::hasColumn('quotes', 'operator_id'))->toBeTrue();
});
