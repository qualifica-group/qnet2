<?php

use App\Enums\MigrationStatus;
use App\Enums\PurchaseInvoiceType;
use App\Enums\PurchaseOrderStatus;
use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\CostType;
use App\Models\MigrationRun;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\PurchaseOrders\PurchaseOrderInvoiceReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ODA fixtures (legacyPurchaseOrderRecord, legacyPurchaseOrderAnchors, ...) live in
// tests/Helpers/PurchaseOrderMigrationHelpers.php: every case first migrates the
// orders the invoice bills through the real `purchase-orders` source.

beforeEach(function () {
    seedMigrationsConfig();
    $this->anchors = legacyPurchaseOrderAnchors();
    $this->paymentMethod = PaymentMethod::factory()->create();
    $this->paymentMethod->forceFill(['old_id' => 76])->save();

    runLegacyPurchaseOrdersImport([
        legacyPurchaseOrderRecord(['lines' => [
            legacyPurchaseOrderLine(),
            legacyPurchaseOrderLine(['id' => 1501, 'position' => 2, 'quantity' => 1, 'unit_price' => 5]),
        ]]),
        legacyPurchaseOrderRecord(['id' => 801, 'number' => 13, 'lines' => [legacyPurchaseOrderLine(['id' => 1502, 'quantity' => 3, 'unit_price' => 2])]]),
        legacyPurchaseOrderRecord(['id' => 802, 'number' => 14, 'status' => 'fulfilled', 'lines' => [legacyPurchaseOrderLine(['id' => 1503])]]),
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyPurchaseInvoiceRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 800,
        'purchase_order_ids' => [800, 801],
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
        'lines' => [
            ['purchase_order_line_id' => 1500, 'description' => 'Invoiced line', 'cost_type_name' => 'Altro', 'quantity' => 2, 'unit_price' => 11, 'vat_rate_id' => null, 'work_order_id' => null],
            ['purchase_order_line_id' => 1501, 'description' => 'Second line', 'cost_type_name' => 'Fitti', 'quantity' => 1, 'unit_price' => 5, 'vat_rate_id' => null, 'work_order_id' => null],
            ['purchase_order_line_id' => 1502, 'description' => 'Other order', 'cost_type_name' => 'Altro', 'quantity' => 3, 'unit_price' => 2, 'vat_rate_id' => null, 'work_order_id' => null],
        ],
        'installments' => [['due_date' => '2025-04-30', 'amount' => 33, 'payment_method_code' => 'MP05']],
        'warnings' => [],
        'created_at' => '2025-03-20 08:00:00',
        'updated_at' => '2025-03-25 08:00:00',
    ], $overrides);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyPurchaseInvoicesImport(array $records, ?User $actor = null): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/purchase-invoices*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'purchase-invoices']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-001 imports a merged invoice billing every order of the group, without activity log', function () {
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord()], $actor);

    $invoice = PurchaseInvoice::query()->where('old_id', 800)->sole();
    $orders = PurchaseOrder::query()->whereIn('old_id', [800, 801])->pluck('id');
    $firstLine = $invoice->lines()->orderBy('sort_order')->first();

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($invoice->document_type)->toBe(PurchaseInvoiceType::Invoice)
        ->and($invoice->document_number)->toBe('FPR 89/25')
        ->and($invoice->document_date->toDateString())->toBe('2025-03-20')
        ->and($invoice->document_year)->toBe(2025)
        ->and($invoice->supplier_id)->toBe($this->anchors['supplier']->id)
        ->and($invoice->company_id)->toBe($this->anchors['company']->id)
        ->and($invoice->company_site_id)->toBe($this->anchors['companySite']->id)
        ->and($invoice->payment_method_id)->toBe($this->paymentMethod->id)
        ->and($invoice->cost_type_id)->toBe(CostType::query()->where('name', 'Altro')->value('id'))
        ->and($invoice->created_by)->toBe($this->anchors['creator']->id)
        ->and($invoice->net_amount)->toBe('33.00')
        ->and($invoice->total_amount)->toBe('33.00')
        ->and($invoice->created_at->toDateTimeString())->toBe('2025-03-20 08:00:00')
        ->and($invoice->purchaseOrders()->pluck('purchase_orders.id')->sort()->values()->all())->toBe($orders->sort()->values()->all())
        ->and($invoice->lines()->count())->toBe(3)
        ->and($firstLine->purchase_order_line_id)->toBe(PurchaseOrderLine::query()->where('old_id', 1500)->value('id'))
        ->and($firstLine->unit_price)->toBe('11.00')
        ->and($invoice->lines()->where('description', 'Second line')->value('cost_type_id'))->toBe(CostType::query()->where('name', 'Fitti')->value('id'))
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);

    $installment = $invoice->installments()->sole();
    expect($installment->sequence)->toBe(1)
        ->and($installment->due_date->toDateString())->toBe('2025-04-30')
        ->and($installment->amount)->toBe('33.00')
        ->and($installment->payment_method_code)->toBe('MP05')
        ->and($installment->paid_amount)->toBeNull();
});

it('AC-002 skips an invoice already imported', function () {
    runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord()]);

    $run = runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord()]);

    expect($run->skipped_rows)->toBe(1)
        ->and(PurchaseInvoice::query()->count())->toBe(1);
});

it('AC-003 fails the row when a reference is missing or a qnet-2 rule is broken', function (array $overrides, string $reason) {
    $run = runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord($overrides)]);

    expect($run->failed_rows)->toBe(1)
        ->and(PurchaseInvoice::query()->count())->toBe(0)
        ->and(PurchaseInvoiceLine::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain($reason);
})->with([
    'supplier' => [['supplier_id' => 99], 'Supplier (legacy id 99) not migrated'],
    'payment method' => [['payment_method_id' => null], 'Payment method is required'],
    'creator' => [['creator_user_id' => 99], 'Creator (legacy id 99) not migrated'],
    'order' => [['purchase_order_ids' => [800, 999]], 'Purchase order (legacy id 999) not migrated'],
    'order line' => [['purchase_order_ids' => [800]], 'Order line (legacy id 1502) not migrated on the invoiced orders'],
    'cost type' => [['cost_type_name' => 'Unknown'], "Header: cost type 'Unknown' not found"],
    'zero total' => [['lines' => [['purchase_order_line_id' => 1500, 'description' => 'Free', 'cost_type_name' => 'Altro', 'quantity' => 2, 'unit_price' => 0]]], 'Total 0.00 is not positive'],
]);

it('AC-003 fails the row when the supplier already used the number in the year', function () {
    PurchaseInvoice::factory()->create([
        'document_type' => PurchaseInvoiceType::Invoice,
        'supplier_id' => $this->anchors['supplier']->id,
        'document_number' => 'FPR 89/25',
        'document_date' => '2025-01-10',
    ]);

    $run = runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord()]);

    expect($run->failed_rows)->toBe(1)
        ->and(legacyTaskErrors($run)[0])->toContain("Number 'FPR 89/25' already used by the supplier in 2025");
});

it('AC-004 orders the legacy installments and lets the last absorb a difference from the total', function () {
    $run = runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord(['installments' => [
        ['due_date' => '2025-04-30', 'amount' => 10, 'payment_method_code' => 'MP05'],
        ['due_date' => '2025-05-31', 'amount' => 20, 'payment_method_code' => 'MP05'],
    ]])]);

    $installments = PurchaseInvoice::query()->sole()->installments()->orderBy('sequence')->get();

    expect($installments->pluck('amount')->all())->toBe(['10.00', '23.00'])
        ->and(legacyTaskWarnings($run))->toBe(['The legacy installments sum to 30.00, the invoice total is 33.00: the last installment absorbs the difference.']);
});

it('AC-004 plans the schedule from the payment method when the legacy has none', function () {
    $run = runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord(['installments' => []])]);

    $invoice = PurchaseInvoice::query()->sole();

    expect($invoice->installments()->count())->toBeGreaterThan(0)
        ->and((float) $invoice->installments()->sum('amount'))->toBe(33.0)
        ->and(legacyTaskWarnings($run))->toBe(['No installment in the legacy: the schedule comes from the payment method.']);
});

it('AC-005 records a paid legacy invoice as fully paid', function () {
    $run = runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord(['is_paid' => true, 'paid_at' => '2025-04-02'])]);

    $installment = PurchaseInvoice::query()->sole()->installments()->sole();

    expect($installment->paid_amount)->toBe('33.00')
        ->and($installment->paid_at->toDateString())->toBe('2025-04-02')
        ->and($installment->financial_account_id)->toBeNull()
        ->and(legacyTaskWarnings($run))->toBe(['Paid in the legacy: every installment is recorded as paid on 2025-04-02.']);
});

it('AC-006 receives what the invoice bills on draft orders, which become fulfilled', function () {
    runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord()]);

    $invoice = PurchaseInvoice::query()->sole();
    $order = PurchaseOrder::query()->where('old_id', 800)->sole();
    $receipt = $order->receipts()->sole();

    expect($order->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and(PurchaseOrder::query()->where('old_id', 801)->sole()->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($receipt->purchase_invoice_id)->toBe($invoice->id)
        ->and($receipt->receipt_date->toDateString())->toBe('2025-03-20')
        ->and($order->lines()->pluck('received_quantity')->all())->toBe(['2.000', '1.000']);
});

it('AC-006 adds no receipt on an order already received at the ODA import', function () {
    runLegacyPurchaseInvoicesImport([legacyPurchaseInvoiceRecord([
        'purchase_order_ids' => [802],
        'lines' => [['purchase_order_line_id' => 1503, 'description' => 'Received', 'cost_type_name' => 'Altro', 'quantity' => 2, 'unit_price' => 5]],
        'installments' => [['due_date' => '2025-04-30', 'amount' => 10, 'payment_method_code' => 'MP05']],
    ])]);

    $receipts = PurchaseOrder::query()->where('old_id', 802)->sole()->receipts()->get();

    expect($receipts)->toHaveCount(1)
        ->and($receipts->first()->purchase_invoice_id)->toBeNull();
});

it('AC-007 receive() still refuses a draft order outside the legacy import', function () {
    $orderLine = PurchaseOrderLine::query()->where('old_id', 1500)->sole();
    $invoice = PurchaseInvoice::factory()->create(['document_type' => PurchaseInvoiceType::Invoice, 'document_date' => '2025-03-20']);
    PurchaseInvoiceLine::factory()->create(['purchase_invoice_id' => $invoice->id, 'purchase_order_line_id' => $orderLine->id]);

    expect(fn () => DB::transaction(fn () => app(PurchaseOrderInvoiceReceiptService::class)->receive($invoice, $this->anchors['creator'], false)))
        ->toThrow(ValidationException::class);
});

it('AC-008 registers the source in the phase after purchase orders with its dependencies', function () {
    $source = app(MigrationRegistry::class)->resolve('purchase-invoices');

    expect(MigrationOrder::phaseOf('purchase-invoices'))->toBe(MigrationOrder::phaseOf('purchase-orders') + 1)
        ->and(array_map(fn ($dependency) => [$dependency->source, $dependency->required], $source->dependencies()))->toBe([
            ['users', true], ['companies', true], ['company-sites', true], ['registries', true], ['payment-methods', true],
            ['purchase-orders', true], ['operational-sites', false], ['vat-rates', false], ['work-orders', false],
        ]);
});
