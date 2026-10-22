<?php

use App\Enums\InvoiceType;
use App\Enums\MigrationStatus;
use App\Enums\ProformaRequestStatus;
use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\MigrationRun;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMigrationsConfig();
    $this->company = Company::factory()->create();
    $this->company->forceFill(['old_id' => 17])->save();
    $this->customer = Registry::factory()->create(['old_id' => 30232]);
    $this->paymentMethod = PaymentMethod::factory()->create();
    $this->paymentMethod->forceFill(['old_id' => 19])->save();
    $this->vatRate = VatRate::factory()->create(['old_id' => 6, 'rate' => 22]);
    $this->creator = User::factory()->create(['old_id' => 98]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyInvoiceRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 11030,
        'source' => 'proformas',
        'type' => 'proforma',
        'number' => 12804,
        'year_max_number' => 12804,
        'document_date' => '2026-10-21',
        'company_id' => 17,
        'customer_id' => 30232,
        'work_order_id' => null,
        'quote_id' => null,
        'payment_method_id' => 19,
        'financial_account_id' => null,
        'proforma_request_id' => null,
        'external_number' => null,
        'external_date' => null,
        'notes' => null,
        'internal_note' => null,
        'deviation' => null,
        'creator_user_id' => 98,
        'lines' => [['product_id' => null, 'quote_line_id' => null, 'description' => 'Consulenza ISO', 'quantity' => 1, 'unit_price' => 830, 'vat_rate_id' => 6]],
        'installments' => [['due_date' => '2026-10-21', 'amount' => 1012.6, 'payment_method_code' => 'MP05', 'collected_amount' => null, 'collected_at' => null]],
        'warnings' => [],
        'created_at' => '2026-10-21 19:41:47',
        'updated_at' => '2026-10-22 08:00:00',
    ], $overrides);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyInvoicesImport(array $records, ?User $actor = null): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/invoices*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'invoices']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-001 imports a registered document with lines, collections and its request, without log', function () {
    $quote = Quote::factory()->create();
    $quote->forceFill(['old_id' => 104988])->save();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->forceFill(['old_id' => 20019])->save();
    $product = Product::factory()->create();
    $product->forceFill(['old_source' => 'services', 'old_id' => 1484])->save();
    $quoteLine = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $quoteLine->forceFill(['old_id' => 55])->save();
    $account = FinancialAccount::factory()->create();
    $account->forceFill(['old_id' => 13])->save();
    $request = ProformaRequest::factory()->create(['work_order_id' => $workOrder->id, 'quote_id' => $quote->id, 'status' => ProformaRequestStatus::Pending]);
    $request->forceFill(['old_id' => 10907])->save();
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyInvoicesImport([legacyInvoiceRecord([
        'type' => 'invoice',
        'work_order_id' => 20019,
        'quote_id' => 104988,
        'financial_account_id' => 13,
        'proforma_request_id' => 10907,
        'external_number' => 'FT 125/26',
        'external_date' => '2026-10-22',
        'notes' => 'Saldo',
        'internal_note' => 'Controllato',
        'deviation' => 12.5,
        'lines' => [
            ['product_id' => 1484, 'quote_line_id' => 55, 'description' => 'Consulenza ISO', 'quantity' => 1, 'unit_price' => 930, 'vat_rate_id' => 6],
            ['product_id' => null, 'quote_line_id' => null, 'description' => 'Sconto', 'quantity' => 1, 'unit_price' => -100, 'vat_rate_id' => 6],
        ],
        'installments' => [['due_date' => '2026-10-21', 'amount' => 1012.6, 'payment_method_code' => 'MP05', 'collected_amount' => 1012.6, 'collected_at' => '2026-10-25']],
    ])], $actor);

    $invoice = Invoice::query()->where('old_source', 'proformas')->where('old_id', 11030)->sole();
    $line = $invoice->lines()->orderBy('sort_order')->first();
    $installment = $invoice->installments()->sole();

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->report)->toBeNull()
        ->and($invoice->type)->toBe(InvoiceType::Invoice)
        ->and($invoice->number)->toBe(12804)
        ->and($invoice->year)->toBe(2026)
        ->and($invoice->company_id)->toBe($this->company->id)
        ->and($invoice->customer_registry_id)->toBe($this->customer->id)
        ->and($invoice->work_order_id)->toBe($workOrder->id)
        ->and($invoice->quote_id)->toBe($quote->id)
        ->and($invoice->payment_method_id)->toBe($this->paymentMethod->id)
        ->and($invoice->financial_account_id)->toBe($account->id)
        ->and($invoice->proforma_request_id)->toBe($request->id)
        ->and($invoice->external_number)->toBe('FT 125/26')
        ->and($invoice->external_date->toDateString())->toBe('2026-10-22')
        ->and($invoice->notes)->toBe('Saldo')
        ->and($invoice->internal_note)->toBe('Controllato')
        ->and($invoice->created_by)->toBe($this->creator->id)
        ->and($invoice->net_amount)->toBe('830.00')
        ->and($invoice->vat_amount)->toBe('182.60')
        ->and($invoice->total_amount)->toBe('1012.60')
        ->and($invoice->created_at->toDateTimeString())->toBe('2026-10-21 19:41:47')
        ->and($line->product_id)->toBe($product->id)
        ->and($line->quote_line_id)->toBe($quoteLine->id)
        ->and($installment->collected_amount)->toBe('1012.60')
        ->and($installment->collected_at->toDateString())->toBe('2026-10-25')
        ->and($request->fresh()->status)->toBe(ProformaRequestStatus::Issued)
        ->and(InvoiceNumberSequence::query()->where('company_id', $this->company->id)->where('year', 2026)->value('last_number'))->toBe(12804)
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});

it('AC-002 skips a document already imported and keeps an institution proforma with the same legacy id apart', function () {
    $records = [legacyInvoiceRecord(), legacyInvoiceRecord(['source' => 'proforma_ente', 'number' => null])];
    $first = runLegacyInvoicesImport($records);

    $second = runLegacyInvoicesImport($records);

    expect($first->created_rows)->toBe(2)
        ->and($second->skipped_rows)->toBe(2)
        ->and(Invoice::query()->where('old_id', 11030)->pluck('old_source')->sort()->values()->all())->toBe(['proforma_ente', 'proformas']);
});

it('AC-003 fails the row when a required reference is missing or the total is not positive', function (array $overrides, string $reason) {
    $run = runLegacyInvoicesImport([legacyInvoiceRecord($overrides)]);

    expect($run->failed_rows)->toBe(1)
        ->and(Invoice::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain($reason);
})->with([
    'company' => [['company_id' => null], 'Company is required'],
    'customer' => [['customer_id' => 99], 'Customer (legacy id 99) not migrated'],
    'payment method' => [['payment_method_id' => 99], 'Payment method (legacy id 99) not migrated'],
    'creator' => [['creator_user_id' => 99], 'Creator (legacy id 99) not migrated'],
    'vat rate' => [['lines' => [['description' => 'No VAT', 'quantity' => 1, 'unit_price' => 10, 'vat_rate_id' => null]]], 'Line 1: VAT rate is required'],
    'zero total' => [['lines' => [['description' => 'Storno', 'quantity' => 1, 'unit_price' => -10, 'vat_rate_id' => 6]]], 'is not positive'],
]);

it('AC-004 numbers an institution proforma past the highest legacy number and renumbers a duplicate', function () {
    $run = runLegacyInvoicesImport([
        legacyInvoiceRecord(),
        legacyInvoiceRecord(['id' => 11031, 'notes' => 'Copia']),
        legacyInvoiceRecord(['id' => 1, 'source' => 'proforma_ente', 'number' => null]),
    ]);

    $duplicate = Invoice::query()->where('old_source', 'proformas')->where('old_id', 11031)->sole();

    expect($duplicate->number)->toBe(12805)
        ->and($duplicate->notes)->toBe("Copia\n\nNumero legacy 12804")
        ->and(Invoice::query()->where('old_source', 'proforma_ente')->sole()->number)->toBe(12806)
        ->and(InvoiceNumberSequence::query()->where('company_id', $this->company->id)->where('year', 2026)->value('last_number'))->toBe(12806)
        ->and(legacyTaskWarnings($run))->toBe(['Number 12804/2026 already used: the document takes 12805/2026.']);
});

it('AC-005 lets the last installment absorb a difference and plans a missing schedule', function () {
    $run = runLegacyInvoicesImport([
        legacyInvoiceRecord(['installments' => [['due_date' => '2026-10-21', 'amount' => 1000, 'payment_method_code' => 'MP05']]]),
        legacyInvoiceRecord(['id' => 11031, 'number' => 12803, 'installments' => []]),
    ]);

    expect(Invoice::query()->where('old_id', 11030)->sole()->installments()->sole()->amount)->toBe('1012.60')
        ->and((float) Invoice::query()->where('old_id', 11031)->sole()->installments()->sum('amount'))->toBe(1012.6)
        ->and(legacyTaskWarnings($run))->toBe([
            'The legacy installments sum to 1000.00, the document total is 1012.60: the last installment absorbs the difference.',
            'No installment in the legacy: the schedule comes from the payment method.',
        ]);
});

it('AC-006 leaves a request already linked to another document empty with a warning', function () {
    $request = ProformaRequest::factory()->create();
    $request->forceFill(['old_id' => 10907])->save();

    $run = runLegacyInvoicesImport([
        legacyInvoiceRecord(['proforma_request_id' => 10907]),
        legacyInvoiceRecord(['id' => 11031, 'number' => 12803, 'proforma_request_id' => 10907]),
    ]);

    expect(Invoice::query()->where('old_id', 11031)->sole()->proforma_request_id)->toBeNull()
        ->and(legacyTaskWarnings($run))->toBe(['Proforma request (legacy id 10907) already linked to another document, left empty.']);
});

it('AC-007 registers the source in the phase after purchase invoices with its dependencies', function () {
    $source = app(MigrationRegistry::class)->resolve('invoices');

    expect(MigrationOrder::phaseOf('invoices'))->toBe(MigrationOrder::phaseOf('purchase-invoices') + 1)
        ->and(array_map(fn ($dependency) => [$dependency->source, $dependency->required], $source->dependencies()))->toBe([
            ['users', true], ['companies', true], ['registries', true], ['payment-methods', true], ['vat-rates', true],
            ['work-orders', false], ['quotes', false], ['products', false], ['financial-accounts', false], ['proforma-requests', false],
        ]);
});
