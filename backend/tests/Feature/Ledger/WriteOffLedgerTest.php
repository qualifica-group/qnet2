<?php

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// Spec 0361, AC-008 — the write-off row of the registry ledger (spec 0266).

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
    foreach (['ledger.view', 'invoices.view', 'purchase-invoices.view'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Sanctum::actingAs(User::factory()->create()->givePermissionTo(['ledger.view', 'invoices.view', 'purchase-invoices.view']));
});

afterEach(fn () => Carbon::setTestNow());

it('AC-008: the customer ledger gets a credit write-off row and the invoice residual is 0', function () {
    $customer = Registry::factory()->create();
    $invoice = Invoice::factory()->asInvoice()->create(['customer_registry_id' => $customer->id, 'document_date' => '2026-03-01', 'total_amount' => '1000.00', 'number' => 7, 'year' => 2026]);
    InvoiceInstallment::factory()->create([
        'invoice_id' => $invoice->id, 'sequence' => 1, 'due_date' => '2026-04-01', 'amount' => '1000.00',
        'collected_amount' => '999.80', 'collected_at' => '2026-04-05', 'write_off_amount' => '0.20', 'write_off_reason' => 'rounding',
    ]);

    $section = $this->getJson("/api/registries/{$customer->id}/ledger?date_from=2026-01-01", ['Accept-Language' => 'it'])->assertOk()->json('data.customer');
    [$document, $writeOff] = $section['documents'];

    expect($section['documents'])->toHaveCount(2)
        ->and($document)->toMatchArray(['document_type' => 'invoice', 'debit' => 1000.0, 'credit' => 999.8, 'residual_amount' => 0.0, 'status' => 'paid'])
        ->and($document['installments'][0])->toMatchArray(['residual_amount' => 0.0, 'write_off_amount' => 0.2, 'days_late' => 0])
        ->and($writeOff)->toMatchArray([
            'document_type' => 'write_off', 'document_date' => '2026-04-05', 'debit' => 0.0, 'credit' => 0.2,
            'residual_amount' => 0.0, 'running_balance' => 0.0, 'number_label' => 'Abbuono 7/2026',
        ])
        ->and($section['summary'])->toMatchArray(['invoiced_total' => 1000.0, 'settled_total' => 999.8, 'write_off_total' => 0.2, 'residual_total' => 0.0, 'overdue_total' => 0.0]);
});

it('AC-008: the supplier ledger gets a debit write-off row labelled Stralcio and totals balance', function () {
    $supplier = Registry::factory()->supplier()->create();
    $document = PurchaseInvoice::factory()->create(['supplier_id' => $supplier->id, 'document_date' => '2026-03-01', 'total_amount' => '500.00', 'document_number' => 'F-12']);
    PurchaseInvoiceInstallment::factory()->create([
        'purchase_invoice_id' => $document->id, 'sequence' => 1, 'due_date' => '2026-04-01', 'amount' => '500.00',
        'paid_amount' => null, 'paid_at' => '2026-04-06', 'write_off_amount' => '500.00', 'write_off_reason' => 'bad_debt',
    ]);

    $section = $this->getJson("/api/registries/{$supplier->id}/ledger?date_from=2026-01-01", ['Accept-Language' => 'it'])->assertOk()->json('data.supplier');
    $writeOff = $section['documents'][1];

    expect($section['documents'][0])->toMatchArray(['residual_amount' => 0.0, 'status' => 'paid'])
        ->and($writeOff)->toMatchArray(['document_type' => 'write_off', 'debit' => 500.0, 'credit' => 0.0, 'number_label' => 'Stralcio F-12', 'document_date' => '2026-04-06'])
        ->and($section['summary']['write_off_total'])->toEqual(500.0)
        ->and($section['summary']['residual_total'])->toEqual(0.0)
        ->and($section['summary']['aging']['overdue_1_30'])->toEqual(0.0);
});
