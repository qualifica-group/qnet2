<?php

use App\Models\Company;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrderLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * A document row as the phase 1 schema stored it (no document_year, no lines).
 *
 * @param  array<string, mixed>  $attributes
 */
function legacyPurchaseInvoiceRow(array $attributes = []): int
{
    return DB::table('purchase_invoices')->insertGetId($attributes + [
        'document_type' => 'invoice',
        'company_id' => Company::factory()->create()->id,
        'supplier_id' => Registry::factory()->supplier()->create()->id,
        'document_number' => 'LEG-'.fake()->unique()->numberBetween(1, 99999),
        'document_date' => '2026-03-15',
        'payment_method_id' => PaymentMethod::factory()->create()->id,
        'net_amount' => '100.00',
        'vat_amount' => '22.00',
        'total_amount' => '122.00',
        'created_by' => User::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('AC-001: rolls back the lines table and the year column, and re-applies them', function () {
    $migration = require database_path('migrations/2026_10_17_180000_create_purchase_invoice_lines_table.php');

    $migration->down();

    expect(Schema::hasTable('purchase_invoice_lines'))->toBeFalse()
        ->and(Schema::hasColumn('purchase_invoices', 'document_year'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('purchase_invoice_lines'))->toBeTrue()
        ->and(Schema::hasColumn('purchase_invoices', 'document_year'))->toBeTrue();
});

it('AC-001: every existing document gets one line equal to its header, and invoices get their year', function () {
    $migration = require database_path('migrations/2026_10_17_180000_create_purchase_invoice_lines_table.php');
    $migration->down();

    $costType = CostType::factory()->create();
    $headerType = CostType::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $invoice = legacyPurchaseInvoiceRow(['document_number' => 'LEG-1', 'cost_type_id' => $headerType->id, 'work_order_id' => $workOrder->id]);
    $proforma = legacyPurchaseInvoiceRow(['document_type' => 'proforma', 'document_number' => null, 'document_date' => '2025-12-31']);

    $migration->up();

    $lines = DB::table('purchase_invoice_lines')->orderBy('purchase_invoice_id')->get()->keyBy('purchase_invoice_id');

    expect($lines)->toHaveCount(2)
        ->and($lines[$invoice]->description)->toBe('Documento LEG-1')
        ->and((float) $lines[$invoice]->net_amount)->toBe(100.0)
        ->and((float) $lines[$invoice]->vat_amount)->toBe(22.0)
        ->and((float) $lines[$invoice]->total_amount)->toBe(122.0)
        ->and((float) $lines[$invoice]->quantity)->toBe(1.0)
        ->and($lines[$invoice]->cost_type_id)->toBe($headerType->id)
        ->and($lines[$invoice]->work_order_id)->toBe($workOrder->id)
        ->and($lines[$proforma]->description)->toBe('Documento')
        ->and($lines[$proforma]->cost_type_id)->toBe(DB::table('cost_types')->orderBy('sort_order')->orderBy('id')->value('id'))
        ->and($costType->id)->not->toBeNull()
        ->and((int) DB::table('purchase_invoices')->where('id', $invoice)->value('document_year'))->toBe(2026)
        ->and(DB::table('purchase_invoices')->where('id', $proforma)->value('document_year'))->toBeNull();
});

it('AC-001: duplicated numbers of the same year keep the year on the first document only', function () {
    $migration = require database_path('migrations/2026_10_17_180000_create_purchase_invoice_lines_table.php');
    $migration->down();

    $supplier = Registry::factory()->supplier()->create();
    $first = legacyPurchaseInvoiceRow(['supplier_id' => $supplier->id, 'document_number' => 'DUP']);
    $second = legacyPurchaseInvoiceRow(['supplier_id' => $supplier->id, 'document_number' => 'DUP']);

    $migration->up();

    expect((int) DB::table('purchase_invoices')->where('id', $first)->value('document_year'))->toBe(2026)
        ->and(DB::table('purchase_invoices')->where('id', $second)->value('document_year'))->toBeNull();
});

it('AC-001: the unique index refuses a duplicated invoice number and ignores the proformas', function () {
    $supplier = Registry::factory()->supplier()->create();
    PurchaseInvoice::factory()->create(['supplier_id' => $supplier->id, 'document_number' => 'U-1']);

    expect(fn () => PurchaseInvoice::factory()->create(['supplier_id' => $supplier->id, 'document_number' => 'U-1']))
        ->toThrow(UniqueConstraintViolationException::class);

    PurchaseInvoice::factory()->proforma()->create(['supplier_id' => $supplier->id, 'document_number' => 'U-1']);
    PurchaseInvoice::factory()->proforma()->create(['supplier_id' => $supplier->id, 'document_number' => 'U-1']);

    expect(PurchaseInvoice::query()->where('document_number', 'U-1')->count())->toBe(3);
});

it('AC-004: the order line index is plain: several document lines may reference one order line', function () {
    $orderLine = PurchaseOrderLine::factory()->create();
    PurchaseInvoiceLine::factory()->count(2)->create(['purchase_order_line_id' => $orderLine->id]);
    PurchaseInvoiceLine::factory()->count(2)->create(['purchase_order_line_id' => null]);

    expect(PurchaseInvoiceLine::query()->where('purchase_order_line_id', $orderLine->id)->count())->toBe(2);
});
