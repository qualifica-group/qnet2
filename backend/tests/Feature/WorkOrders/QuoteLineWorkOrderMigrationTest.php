<?php

use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AC-041: the `quote_line_work_order` pivot migration
 * (2026_09_02_210000_change_quote_line_work_order_unique_constraint) detects
 * pre-existing data that would violate its new UNIQUE(quote_line_id) and
 * aborts with an explicit, readable exception — instead of letting the
 * `unique()` call surface an opaque DB integrity error.
 *
 * RefreshDatabase has already run the migration once (the schema is on the
 * NEW, single-column constraint by the time this test starts) — down() rolls
 * it back to the OLD pair constraint so a genuine conflict (same
 * quote_line_id, two DIFFERENT work_order_ids) can be planted, exactly the
 * state D-4a describes as possible in production data.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteLineWorkOrderMigration')) {
    function quoteLineWorkOrderMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_09_02_210000_change_quote_line_work_order_unique_constraint.php');

        return $migration;
    }
}

it('AC-041: up() aborts with a readable exception naming the conflicting quote_line_id, schema left untouched', function () {
    $migration = quoteLineWorkOrderMigration();
    $migration->down();

    $line = QuoteLine::factory()->create();
    $workOrderA = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
    $workOrderB = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);

    // Allowed under the OLD pair constraint: same quote_line_id, two
    // DIFFERENT work_order_ids — exactly the conflict D-4a warns about.
    DB::table('quote_line_work_order')->insert([
        ['work_order_id' => $workOrderA->id, 'quote_line_id' => $line->id],
        ['work_order_id' => $workOrderB->id, 'quote_line_id' => $line->id],
    ]);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, (string) $line->id);

    expect(Schema::hasColumn('quote_line_work_order', 'quote_line_id'))->toBeTrue();
    $this->assertDatabaseCount('quote_line_work_order', 2);
});

it('up() succeeds and enforces the new constraint once no conflicting data remains', function () {
    $migration = quoteLineWorkOrderMigration();
    $migration->down();

    $line = QuoteLine::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
    DB::table('quote_line_work_order')->insert(['work_order_id' => $workOrder->id, 'quote_line_id' => $line->id]);

    $migration->up();

    $anotherWorkOrder = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);

    expect(fn () => DB::table('quote_line_work_order')->insert([
        'work_order_id' => $anotherWorkOrder->id, 'quote_line_id' => $line->id,
    ]))->toThrow(Exception::class);
});
