<?php

use App\Models\ProformaRequest;
use App\Models\WorkOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Spec 0267 (D-4, D-6, AC-012). RefreshDatabase already ran the migrations;
 * the files are required again to drive up()/down() by hand.
 */
function multiQuoteMigration(string $file): Migration
{
    return require database_path("migrations/{$file}.php");
}

it('accepts a work order without quote_id', function () {
    $workOrder = WorkOrder::factory()->multiQuote()->create();

    expect($workOrder->fresh()->quote_id)->toBeNull()
        ->and($workOrder->isMultiQuote())->toBeTrue();
});

it('refuses to roll back quote_id nullability while a multi-offer work order exists', function () {
    WorkOrder::factory()->multiQuote()->create();

    expect(fn () => multiQuoteMigration('2026_10_19_100000_make_work_orders_quote_id_nullable')->down())
        ->toThrow(RuntimeException::class, '1 multi-offer work order');
});

it('rolls back quote_id nullability when every work order has an offer', function () {
    WorkOrder::factory()->create();

    multiQuoteMigration('2026_10_19_100000_make_work_orders_quote_id_nullable')->down();

    expect(fn () => DB::table('work_orders')->insert(['title' => 'x', 'type' => 'standard', 'start_date' => now(), 'quote_id' => null, 'code' => 'COM-X']))
        ->toThrow(QueryException::class);
});

it('AC-012: backfills proforma_requests.quote_id from the work order', function () {
    $proforma = ProformaRequest::factory()->create();
    $quoteId = WorkOrder::query()->whereKey($proforma->work_order_id)->value('quote_id');
    $migration = multiQuoteMigration('2026_10_19_100100_add_quote_id_to_proforma_requests_table');

    $migration->down();
    expect(DB::getSchemaBuilder()->hasColumn('proforma_requests', 'quote_id'))->toBeFalse();

    $migration->up();
    expect(DB::table('proforma_requests')->where('id', $proforma->id)->value('quote_id'))->toBe($quoteId);
});
