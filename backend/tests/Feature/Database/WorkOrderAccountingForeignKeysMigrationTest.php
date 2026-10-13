<?php

use App\Models\Invoice;
use App\Models\ProformaRequest;
use App\Models\WorkOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Spec 0243 AC-018. RefreshDatabase already ran the migration; the file is
 * required again to drive up()/down() by hand inside the per-test transaction.
 */
function restrictForeignKeysMigration(): Migration
{
    return require database_path('migrations/2026_10_17_100000_restrict_work_order_accounting_foreign_keys.php');
}

function proformaWithInvoice(): ProformaRequest
{
    $proforma = ProformaRequest::factory()->create();
    Invoice::factory()->create(['proforma_request_id' => $proforma->id, 'work_order_id' => $proforma->work_order_id]);

    return $proforma;
}

it('AC-018: deleting a work order that has a proforma request fails at DB level', function () {
    $proforma = ProformaRequest::factory()->create();

    expect(fn () => DB::table('work_orders')->where('id', $proforma->work_order_id)->delete())
        ->toThrow(QueryException::class);
    expect(WorkOrder::query()->whereKey($proforma->work_order_id)->exists())->toBeTrue();
});

it('AC-018: deleting a proforma request that has an invoice fails at DB level', function () {
    $proforma = proformaWithInvoice();

    expect(fn () => DB::table('proforma_requests')->where('id', $proforma->id)->delete())
        ->toThrow(QueryException::class);
});

it('AC-018: deleting a work order that has an invoice fails at DB level', function () {
    $invoice = Invoice::factory()->create(['work_order_id' => WorkOrder::factory()->create()->id]);

    expect(fn () => DB::table('work_orders')->where('id', $invoice->work_order_id)->delete())
        ->toThrow(QueryException::class);
});

it('AC-018: rollback restores cascade / set-null and keeps the unique index, re-migrate restores restrict', function () {
    $migration = restrictForeignKeysMigration();

    $migration->down();
    $proforma = proformaWithInvoice();
    $invoiceId = Invoice::query()->where('proforma_request_id', $proforma->id)->value('id');

    DB::table('proforma_requests')->where('id', $proforma->id)->delete();
    expect(DB::table('invoices')->where('id', $invoiceId)->value('proforma_request_id'))->toBeNull();

    $other = ProformaRequest::factory()->create();
    DB::table('work_orders')->where('id', $other->work_order_id)->delete();
    expect(DB::table('proforma_requests')->where('id', $other->id)->exists())->toBeFalse();

    $migration->up();
    $kept = ProformaRequest::factory()->create();
    expect(fn () => DB::table('work_orders')->where('id', $kept->work_order_id)->delete())->toThrow(QueryException::class);

    $uniqueIndexes = collect(Schema::getIndexes('invoices'))->where('unique', true)->pluck('columns');
    expect($uniqueIndexes->contains(['proforma_request_id']))->toBeTrue();
});
