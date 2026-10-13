<?php

use App\Models\CostType;
use App\Models\PurchaseInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('AC-005: deleting a cost type used by a purchase invoice fails at DB level', function () {
    $invoice = PurchaseInvoice::factory()->create(['cost_type_id' => CostType::factory()->create()->id]);

    expect(fn () => DB::table('cost_types')->where('id', $invoice->cost_type_id)->delete())
        ->toThrow(QueryException::class);
});

it('AC-005: rollback restores set-null, re-migrate restores restrict, column stays nullable', function () {
    $migration = require database_path('migrations/2026_10_17_110000_restrict_purchase_invoice_cost_type_foreign_key.php');

    $migration->down();
    $invoice = PurchaseInvoice::factory()->create(['cost_type_id' => CostType::factory()->create()->id]);
    DB::table('cost_types')->where('id', $invoice->cost_type_id)->delete();
    expect(DB::table('purchase_invoices')->where('id', $invoice->id)->value('cost_type_id'))->toBeNull();

    $migration->up();
    $kept = PurchaseInvoice::factory()->create(['cost_type_id' => CostType::factory()->create()->id]);
    expect(fn () => DB::table('cost_types')->where('id', $kept->cost_type_id)->delete())->toThrow(QueryException::class);

    expect(PurchaseInvoice::factory()->create(['cost_type_id' => null])->cost_type_id)->toBeNull();
});
