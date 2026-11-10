<?php

use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\PurchaseInvoicesTarget;
use App\Models\MigrationRun;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyRealignSupplierInvoice(array $overrides = []): array
{
    return array_merge([
        'id' => 800,
        'purchase_order_ids' => [800],
        'document_number' => 'FPR 89/25',
        'document_date' => '2025-03-20',
        'supplier_id' => 73,
        'company_id' => 71,
        'company_site_id' => 72,
        'operational_site_id' => null,
        'payment_method_id' => 76,
        'cost_type_name' => 'Altro',
        'is_paid' => false,
        'paid_at' => null,
        'creator_user_id' => 70,
        'lines' => [['purchase_order_line_id' => 1500, 'description' => 'Invoiced line', 'cost_type_name' => 'Altro', 'quantity' => 2, 'unit_price' => 10.5, 'vat_rate_id' => null, 'work_order_id' => null]],
        'installments' => [['due_date' => '2025-04-30', 'amount' => 21, 'payment_method_code' => 'MP05']],
        'warnings' => [],
        'created_at' => '2025-03-20 08:00:00',
        'updated_at' => '2025-03-21 08:00:00',
    ], $overrides);
}

beforeEach(function () {
    seedMigrationsConfig();
    legacyPurchaseOrderAnchors();
    $paymentMethod = PaymentMethod::factory()->create();
    $paymentMethod->forceFill(['old_id' => 76])->save();
    runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord()]);

    Http::swap(new Factory);
    Http::fake([fakeMigrationsBaseUrl().'/purchase-invoices*' => Http::response([
        'items' => [legacyRealignSupplierInvoice()],
        'pagination' => ['total' => 1],
    ])]);
    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'purchase-invoices']);
    runMigrationJobFor($run);
    expect($run->fresh()->created_rows)->toBe(1);
    Http::swap(new Factory);
});

/**
 * @param  array<string, mixed>  $legacy
 */
function realignSupplierInvoice(array $legacy, bool $apply = true): LegacyRealignReport
{
    fakeLegacyRealign('purchase-invoices', [$legacy['id']], [], [$legacy]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(PurchaseInvoicesTarget::class), '2026-10-23', $apply, $report);
    Http::swap(new Factory);

    return $report;
}

it('AC-017 aligns the supplier number, date, schedule and payment', function () {
    $report = realignSupplierInvoice(legacyRealignSupplierInvoice([
        'document_number' => 'FPR 90/25',
        'document_date' => '2025-03-22',
        'is_paid' => true,
        'paid_at' => '2026-10-25',
        'installments' => [['due_date' => '2025-05-31', 'amount' => 21, 'payment_method_code' => 'MP05']],
    ]));

    $invoice = PurchaseInvoice::query()->where('old_id', 800)->firstOrFail();
    $installment = $invoice->installments()->firstOrFail();

    expect($report->counters['purchase-invoices'][LegacyRealignReport::ALIGNED])->toBe(1)
        ->and($invoice->document_number)->toBe('FPR 90/25')
        ->and($invoice->document_date->toDateString())->toBe('2025-03-22')
        ->and($invoice->document_year)->toBe(2025)
        ->and($installment->due_date->toDateString())->toBe('2025-05-31')
        ->and((float) $installment->paid_amount)->toBe(21.0)
        ->and($installment->paid_at->toDateString())->toBe('2026-10-25');
});

it('AC-017 never writes lines or amounts and says when they differ', function () {
    $report = realignSupplierInvoice(legacyRealignSupplierInvoice([
        'lines' => [['purchase_order_line_id' => 1500, 'description' => 'Invoiced line', 'cost_type_name' => 'Altro', 'quantity' => 3, 'unit_price' => 10.5, 'vat_rate_id' => null, 'work_order_id' => null]],
    ]));

    expect((float) PurchaseInvoice::query()->where('old_id', 800)->value('total_amount'))->toBe(21.0)
        ->and(array_column($report->messages, 3))->toContain('Legacy lines or amounts differ (legacy total 31.50, qnet-2 21.00); not updated.');
});

it('keeps a payment recorded on qnet-2 that the legacy does not have', function () {
    $invoice = PurchaseInvoice::query()->where('old_id', 800)->firstOrFail();
    $invoice->installments()->update(['paid_amount' => 10, 'paid_at' => '2026-10-20']);

    $report = realignSupplierInvoice(legacyRealignSupplierInvoice());
    $installment = $invoice->installments()->firstOrFail();

    expect((float) $installment->paid_amount)->toBe(10.0)
        ->and($installment->paid_at->toDateString())->toBe('2026-10-20')
        ->and(array_column($report->messages, 3))->toContain('Installment 1: the payment on qnet-2 differs from the legacy; kept.');
});

it('leaves number and date when the supplier already uses that number in the year', function () {
    $invoice = PurchaseInvoice::query()->where('old_id', 800)->firstOrFail();
    $other = $invoice->replicate();
    $other->forceFill(['old_id' => null, 'document_number' => 'TAKEN'])->saveQuietly();

    $report = realignSupplierInvoice(legacyRealignSupplierInvoice(['document_number' => 'TAKEN']));

    expect($invoice->fresh()->document_number)->toBe('FPR 89/25')
        ->and(array_column($report->messages, 3))->toContain("Number 'TAKEN' already used by the supplier in 2025; number and date kept.");
});

it('finds nothing to do on a second run', function () {
    $legacy = legacyRealignSupplierInvoice(['installments' => [['due_date' => '2025-05-31', 'amount' => 21, 'payment_method_code' => 'MP05']]]);

    realignSupplierInvoice($legacy);
    $second = realignSupplierInvoice($legacy);

    expect($second->counters['purchase-invoices'][LegacyRealignReport::UNCHANGED])->toBe(1);
});
