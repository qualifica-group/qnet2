<?php

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// work_orders — AC-001
// ---------------------------------------------------------------------------

it('schema: work_orders has every declared column, code unique, quote_id restrictOnDelete (AC-001)', function () {
    foreach (['id', 'code', 'quote_id', 'title', 'type', 'callback_date', 'description', 'internal_notes', 'is_force_closed', 'force_close_reason', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('work_orders', $column))->toBeTrue("missing column {$column}");
    }

    $quote = Quote::factory()->create();

    DB::table('work_orders')->insert([
        'code' => 'COM-9001', 'quote_id' => $quote->id, 'title' => 'Prima commessa', 'type' => 'processing',
        'is_force_closed' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('work_orders')->insert([
        'code' => 'COM-9001', 'quote_id' => $quote->id, 'title' => 'Duplicate code', 'type' => 'processing',
        'is_force_closed' => false, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('quotes')->where('id', $quote->id)->delete())->toThrow(QueryException::class);
});

it('migration: down() drops work_orders, up() recreates it empty (AC-001)', function () {
    $migration = require database_path('migrations/2026_09_02_100000_create_work_orders_table.php');

    $migration->down();
    expect(Schema::hasTable('work_orders'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('work_orders'))->toBeTrue();
    expect(DB::table('work_orders')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// quote_line_work_order — AC-002
// ---------------------------------------------------------------------------

it('schema: quote_line_work_order has id/work_order_id/quote_line_id + UNIQUE(work_order_id, quote_line_id) (AC-002)', function () {
    foreach (['id', 'work_order_id', 'quote_line_id'] as $column) {
        expect(Schema::hasColumn('quote_line_work_order', $column))->toBeTrue("missing column {$column}");
    }

    $workOrder = WorkOrder::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);

    DB::table('quote_line_work_order')->insert(['work_order_id' => $workOrder->id, 'quote_line_id' => $line->id]);

    expect(fn () => DB::table('quote_line_work_order')->insert(['work_order_id' => $workOrder->id, 'quote_line_id' => $line->id]))
        ->toThrow(QueryException::class);
});

it('migration: down() drops quote_line_work_order, up() recreates it empty (AC-002)', function () {
    $migration = require database_path('migrations/2026_09_02_100100_create_quote_line_work_order_table.php');

    $migration->down();
    expect(Schema::hasTable('quote_line_work_order'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('quote_line_work_order'))->toBeTrue();
    expect(DB::table('quote_line_work_order')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// cascade on delete — AC-003
// ---------------------------------------------------------------------------

it('deleting a work order cascades its pivot rows, leaves quote_lines/quote intact (AC-003)', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $workOrder->quoteLines()->attach($line->id);

    $quoteId = $workOrder->quote_id;

    $workOrder->delete();

    $this->assertDatabaseMissing('quote_line_work_order', ['work_order_id' => $workOrder->id]);
    $this->assertDatabaseHas('quote_lines', ['id' => $line->id]);
    $this->assertDatabaseHas('quotes', ['id' => $quoteId]);
});

it('deleting a quote_line cascades its pivot row, leaves the work order intact (AC-003)', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $workOrder->quoteLines()->attach($line->id);

    $line->delete();

    $this->assertDatabaseMissing('quote_line_work_order', ['quote_line_id' => $line->id]);
    $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id]);
});
