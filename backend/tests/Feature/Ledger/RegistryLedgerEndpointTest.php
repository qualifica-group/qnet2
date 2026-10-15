<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\OperationalSite;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0266 — GET /api/registries/{registry}/ledger. "Today" is frozen on
// 2026-10-15 so the aging and the statuses are deterministic.
uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<int, string>  $permissions
 */
function ledgerActor(array $permissions = ['ledger.view', 'invoices.view', 'purchase-invoices.view']): User
{
    foreach ($permissions as $name) {
        Permission::findOrCreate($name, 'web');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    Sanctum::actingAs($user);

    return $user;
}

function ledgerUrl(Registry $registry, string $query = ''): string
{
    return "/api/registries/{$registry->id}/ledger".($query === '' ? '' : "?{$query}");
}

/**
 * An invoice (not a proforma unless asked) of the registry with its installments
 * given as [due_date, amount, collected_amount|null].
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<int, array{0: string, 1: string, 2?: string|null, 3?: string|null}>  $rows
 */
function ledgerInvoice(Registry $registry, string $date, string $total, array $rows, array $attributes = [], bool $proforma = false): Invoice
{
    $factory = $proforma ? Invoice::factory() : Invoice::factory()->asInvoice();
    $invoice = $factory->create($attributes + ['customer_registry_id' => $registry->id, 'document_date' => $date, 'total_amount' => $total]);

    foreach ($rows as $index => $row) {
        InvoiceInstallment::factory()->create([
            'invoice_id' => $invoice->id,
            'sequence' => $index + 1,
            'due_date' => $row[0],
            'amount' => $row[1],
            'collected_amount' => $row[2] ?? null,
            'collected_at' => $row[3] ?? null,
        ]);
    }

    return $invoice;
}

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<int, array{0: string, 1: string, 2?: string|null, 3?: string|null}>  $rows
 */
function ledgerPurchase(Registry $supplier, string $date, string $total, array $rows, array $attributes = []): PurchaseInvoice
{
    $document = PurchaseInvoice::factory()->create($attributes + ['supplier_id' => $supplier->id, 'document_date' => $date, 'total_amount' => $total]);

    foreach ($rows as $index => $row) {
        PurchaseInvoiceInstallment::factory()->create([
            'purchase_invoice_id' => $document->id,
            'sequence' => $index + 1,
            'due_date' => $row[0],
            'amount' => $row[1],
            'paid_amount' => $row[2] ?? null,
            'paid_at' => $row[3] ?? null,
        ]);
    }

    return $document;
}

it('AC-001: lists the customer documents in date order with debit, credit, running balance and status', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    ledgerInvoice($customer, '2026-03-01', '1000.00', [['2026-04-01', '1000.00', '1000.00', '2026-04-02']]);
    ledgerInvoice($customer, '2026-05-01', '500.00', [['2026-10-10', '500.00']]);

    $data = $this->getJson(ledgerUrl($customer, 'date_from=2026-01-01'))->assertOk()->json('data');

    expect($data['customer']['documents'])->toHaveCount(2)
        ->and(array_column($data['customer']['documents'], 'debit'))->toEqual([1000.0, 500.0])
        ->and(array_column($data['customer']['documents'], 'credit'))->toEqual([1000.0, 0.0])
        ->and(array_column($data['customer']['documents'], 'running_balance'))->toEqual([0.0, 500.0])
        ->and(array_column($data['customer']['documents'], 'status'))->toEqual(['paid', 'overdue'])
        ->and($data['today'])->toEqual('2026-10-15')
        ->and($data['registry'])->toEqual(['id' => $customer->id, 'name' => $customer->name, 'is_supplier' => false]);
});

it('AC-001: returns the contract shape of a document and its installment', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    ledgerInvoice($customer, '2026-03-01', '100.00', [['2026-10-10', '100.00']], ['number' => 7, 'year' => 2026, 'external_number' => 'EXT-9']);

    $document = $this->getJson(ledgerUrl($customer, 'date_from=2026-01-01'))->assertOk()->json('data.customer.documents.0');

    expect($document['number_label'])->toEqual('7/2026')
        ->and($document['external_number'])->toEqual('EXT-9')
        ->and($document['document_type'])->toEqual('invoice')
        ->and($document['installments'][0])->toMatchArray([
            'sequence' => 1, 'due_date' => '2026-10-10', 'amount' => 100.0, 'settled_amount' => null,
            'settled_at' => null, 'residual_amount' => 100.0, 'days_late' => 5,
        ]);
});

it('AC-002: shows a partly paid supplier document in credit with the payment in debit', function () {
    ledgerActor();
    $supplier = Registry::factory()->supplier()->create();
    ledgerPurchase($supplier, '2026-03-01', '800.00', [['2026-04-01', '300.00', '300.00', '2026-04-02'], ['2026-12-01', '500.00']]);

    $document = $this->getJson(ledgerUrl($supplier, 'date_from=2026-01-01'))->assertOk()->json('data.supplier.documents.0');

    expect($document['credit'])->toEqual(800.0)
        ->and($document['debit'])->toEqual(300.0)
        ->and($document['residual_amount'])->toEqual(500.0)
        ->and($document['running_balance'])->toEqual(500.0)
        ->and($document['installments'][0]['settled_amount'])->toEqual(300.0)
        ->and($document['installments'][0]['settled_at'])->toEqual('2026-04-02');
});

it('AC-003: a document before date_from becomes the opening balance', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    ledgerInvoice($customer, '2025-11-01', '500.00', [['2025-12-01', '500.00', '300.00', '2025-12-05']]);
    ledgerInvoice($customer, '2026-02-01', '100.00', [['2026-11-01', '100.00']]);

    $customerSection = $this->getJson(ledgerUrl($customer, 'date_from=2026-01-01'))->assertOk()->json('data.customer');

    expect($customerSection['opening_balance'])->toEqual(200.0)
        ->and($customerSection['documents'])->toHaveCount(1)
        ->and($customerSection['documents'][0]['running_balance'])->toEqual(300.0);
});

it('AC-004: a document after date_to is out of documents, opening balance and summary', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    ledgerInvoice($customer, '2026-02-01', '100.00', [['2026-11-01', '100.00']]);
    ledgerInvoice($customer, '2026-08-01', '900.00', [['2026-11-01', '900.00']]);

    $section = $this->getJson(ledgerUrl($customer, 'date_from=2026-01-01&date_to=2026-06-30'))->assertOk()->json('data.customer');

    expect($section['documents'])->toHaveCount(1)
        ->and($section['opening_balance'])->toEqual(0.0)
        ->and($section['summary']['invoiced_total'])->toEqual(100.0)
        ->and($section['summary']['residual_total'])->toEqual(100.0)
        ->and($section['summary']['aging']['due_0_30'] + $section['summary']['aging']['due_31_60'] + $section['summary']['aging']['due_over_60'])->toEqual(100.0);
});

it('AC-005: a collection registered after date_to still counts (situation as of today)', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    ledgerInvoice($customer, '2026-02-01', '100.00', [['2026-03-01', '100.00', '100.00', '2026-09-20']]);

    $document = $this->getJson(ledgerUrl($customer, 'date_from=2026-01-01&date_to=2026-06-30'))->assertOk()->json('data.customer.documents.0');

    expect($document['credit'])->toEqual(100.0)
        ->and($document['residual_amount'])->toEqual(0.0)
        ->and($document['status'])->toEqual('paid');
});

it('AC-006: document_type filters proforma and invoices, default invoice', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    ledgerInvoice($customer, '2026-02-01', '100.00', [['2026-11-01', '100.00']]);
    ledgerInvoice($customer, '2026-02-02', '200.00', [['2026-11-01', '200.00']], [], true);

    $types = fn (string $query): array => array_column($this->getJson(ledgerUrl($customer, $query))->assertOk()->json('data.customer.documents'), 'document_type');

    expect($types('date_from=2026-01-01'))->toEqual(['invoice'])
        ->and($types('document_type=invoice'))->toEqual(['invoice'])
        ->and($types('document_type=proforma'))->toEqual(['proforma'])
        ->and($types('document_type=all'))->toEqual(['invoice', 'proforma']);
});

it('AC-006: the supplier side honours the document type too', function () {
    ledgerActor();
    $supplier = Registry::factory()->supplier()->create();
    ledgerPurchase($supplier, '2026-02-01', '100.00', [['2026-11-01', '100.00']]);
    ledgerPurchase($supplier, '2026-02-02', '200.00', [['2026-11-01', '200.00']], ['document_type' => 'proforma', 'document_number' => null]);

    $types = fn (string $query): array => array_column($this->getJson(ledgerUrl($supplier, $query))->assertOk()->json('data.supplier.documents'), 'document_type');

    expect($types(''))->toEqual(['invoice'])
        ->and($types('document_type=proforma'))->toEqual(['proforma'])
        ->and($types('document_type=all'))->toEqual(['invoice', 'proforma']);
});

it('AC-007: filters by company and by the sites, from the quote on the active side', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    $company = Company::factory()->create();
    $site = CompanySite::factory()->create();
    $operational = OperationalSite::factory()->create(['alias' => 'HQ']);
    $quote = Quote::factory()->create(['company_site_id' => $site->id, 'operational_site_id' => $operational->id]);
    $matching = ledgerInvoice($customer, '2026-02-01', '100.00', [['2026-11-01', '100.00']], ['company_id' => $company->id, 'quote_id' => $quote->id]);
    ledgerInvoice($customer, '2026-02-02', '200.00', [['2026-11-01', '200.00']]);

    $ids = fn (string $query): array => array_column($this->getJson(ledgerUrl($customer, $query))->assertOk()->json('data.customer.documents'), 'id');

    expect($ids("company_id[]={$company->id}"))->toEqual([$matching->id])
        ->and($ids("company_site_id[]={$site->id}"))->toEqual([$matching->id])
        ->and($ids("operational_site_id[]={$operational->id}"))->toEqual([$matching->id])
        ->and($ids("company_site_id[]={$site->id}&operational_site_id[]=".OperationalSite::factory()->create()->id))->toEqual([]);

    $document = $this->getJson(ledgerUrl($customer, "company_site_id[]={$site->id}"))->json('data.customer.documents.0');
    expect($document['company'])->toEqual(['id' => $company->id, 'name' => $company->denomination])
        ->and($document['company_site'])->toEqual(['id' => $site->id, 'name' => $site->name])
        ->and($document['operational_site'])->toEqual(['id' => $operational->id, 'name' => 'HQ']);
});

it('AC-007: the filters also narrow the opening balance and the summary, and read the supplier sites from the document', function () {
    ledgerActor();
    $supplier = Registry::factory()->supplier()->create();
    $site = CompanySite::factory()->create();
    ledgerPurchase($supplier, '2025-06-01', '300.00', [['2026-11-01', '300.00']], ['company_site_id' => $site->id]);
    ledgerPurchase($supplier, '2025-06-02', '700.00', [['2026-11-01', '700.00']]);
    ledgerPurchase($supplier, '2026-02-01', '50.00', [['2026-11-01', '50.00']], ['company_site_id' => $site->id]);
    ledgerPurchase($supplier, '2026-02-02', '60.00', [['2026-11-01', '60.00']]);

    $section = $this->getJson(ledgerUrl($supplier, "date_from=2026-01-01&company_site_id[]={$site->id}"))->assertOk()->json('data.supplier');

    expect($section['opening_balance'])->toEqual(300.0)
        ->and($section['documents'])->toHaveCount(1)
        ->and($section['summary']['residual_total'])->toEqual(350.0);
});

it('AC-008: ages the open installments of the registry into the buckets', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    $rows = [];
    foreach ([-10, -45, -75, -120, 0, 40, 90] as $index => $offset) {
        $rows[] = [Carbon::today()->addDays($offset)->toDateString(), (string) (($index + 1) * 100).'.00'];
    }
    $rows[] = ['2026-10-01', '999.00', '999.00', '2026-10-02'];
    ledgerInvoice($customer, '2026-01-10', '3799.00', $rows);

    $summary = $this->getJson(ledgerUrl($customer, 'date_from=2026-01-01'))->assertOk()->json('data.customer.summary');

    expect($summary['aging'])->toEqual([
        'overdue_1_30' => 100.0, 'overdue_31_60' => 200.0, 'overdue_61_90' => 300.0, 'overdue_over_90' => 400.0,
        'due_0_30' => 500.0, 'due_31_60' => 600.0, 'due_over_60' => 700.0,
    ])->and($summary['overdue_total'])->toEqual(1000.0)
        ->and($summary['not_due_total'])->toEqual(1800.0);
});

it('AC-009: the summary adds up the period and the opening balance', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    ledgerInvoice($customer, '2025-11-01', '200.00', [['2026-09-25', '200.00']]);
    ledgerInvoice($customer, '2026-02-01', '1000.00', [['2026-03-01', '600.00', '600.00', '2026-03-02'], ['2026-12-01', '400.00']]);

    $section = $this->getJson(ledgerUrl($customer, 'date_from=2026-01-01'))->assertOk()->json('data.customer');

    expect($section['summary']['invoiced_total'])->toEqual(1000.0)
        ->and($section['summary']['settled_total'])->toEqual(600.0)
        ->and($section['summary']['residual_total'])->toEqual(600.0)
        ->and($section['summary']['residual_total'])->toEqual($section['opening_balance'] + 400.0)
        ->and($section['summary']['residual_total'])->toEqual($section['documents'][0]['running_balance'])
        ->and($section['summary']['aging']['overdue_1_30'])->toEqual(200.0);
});

it('AC-010: the document status follows the oldest open installment', function (string $due, string $status, int $daysLate) {
    ledgerActor();
    $customer = Registry::factory()->create();
    ledgerInvoice($customer, '2026-01-10', '300.00', [['2026-01-20', '100.00', '100.00', '2026-01-21'], [$due, '200.00']]);

    $document = $this->getJson(ledgerUrl($customer, 'date_from=2026-01-01'))->assertOk()->json('data.customer.documents.0');

    expect($document['status'])->toEqual($status)
        ->and($document['installments'][1]['days_late'])->toEqual($daysLate)
        ->and($document['installments'][0]['days_late'])->toEqual(0);
})->with([
    'overdue 5 days' => ['2026-10-10', 'overdue', 5],
    'seriously overdue 30 days' => ['2026-09-15', 'seriously_overdue', 30],
    'not due' => ['2026-10-20', 'not_due', 0],
]);

it('AC-011: no supplier section for a registry that is not a supplier and has no payable document', function () {
    ledgerActor();
    $customer = Registry::factory()->create();

    $data = $this->getJson(ledgerUrl($customer))->assertOk()->json('data');

    expect($data['supplier'])->toBeNull()
        ->and($data['customer']['documents'])->toEqual([]);
});

it('AC-011: a supplier registry gets an empty supplier section, and a customer with payable documents gets it too', function () {
    ledgerActor();
    $supplier = Registry::factory()->supplier()->create();
    $mixed = Registry::factory()->create();
    ledgerPurchase($mixed, '2026-02-01', '50.00', [['2026-11-01', '50.00']]);

    $empty = $this->getJson(ledgerUrl($supplier))->assertOk()->json('data.supplier');

    expect($empty['documents'])->toEqual([])
        ->and($empty['opening_balance'])->toEqual(0.0)
        ->and($empty['summary']['residual_total'])->toEqual(0.0)
        ->and($this->getJson(ledgerUrl($mixed, 'date_from=2026-01-01'))->json('data.supplier.documents'))->toHaveCount(1);
});

it('AC-012: 401 without authentication, 403 without ledger.view', function () {
    $registry = Registry::factory()->create();

    $this->getJson(ledgerUrl($registry))->assertUnauthorized();

    ledgerActor(['invoices.view', 'purchase-invoices.view']);
    $this->getJson(ledgerUrl($registry))->assertForbidden();
});

it('AC-012: each section needs its own module permission, and one of them is required', function () {
    $supplier = Registry::factory()->supplier()->create();

    ledgerActor(['ledger.view', 'invoices.view']);
    $this->getJson(ledgerUrl($supplier))->assertOk()
        ->assertJsonPath('data.supplier', null)
        ->assertJsonPath('data.customer.documents', []);

    ledgerActor(['ledger.view', 'purchase-invoices.view']);
    $this->getJson(ledgerUrl($supplier))->assertOk()
        ->assertJsonPath('data.customer', null)
        ->assertJsonPath('data.supplier.documents', []);

    ledgerActor(['ledger.view']);
    $this->getJson(ledgerUrl($supplier))->assertForbidden();
});

it('AC-013: rejects invalid parameters with 422 and a missing registry with 404', function (string $query) {
    ledgerActor();
    $registry = Registry::factory()->create();

    $this->getJson(ledgerUrl($registry, $query))->assertUnprocessable()->assertJsonMissingPath('data');
})->with([
    'bad document type' => 'document_type=x',
    'malformed date' => 'date_from=15-10-2026',
    'date_to before date_from' => 'date_from=2026-05-01&date_to=2026-04-01',
    'unknown company' => 'company_id[]=999999',
    'unknown company site' => 'company_site_id[]=999999',
    'unknown operational site' => 'operational_site_id[]=999999',
]);

it('AC-013: answers 404 for a registry that does not exist', function () {
    ledgerActor();

    $this->getJson('/api/registries/999999/ledger')->assertNotFound();
});

it('keeps the number of queries constant as the documents grow', function () {
    ledgerActor();
    $customer = Registry::factory()->create();
    $count = function () use ($customer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(ledgerUrl($customer, 'date_from=2026-01-01'))->assertOk();

        return count(DB::getQueryLog());
    };

    ledgerInvoice($customer, '2026-02-01', '100.00', [['2026-11-01', '100.00']]);
    $count(); // warm-up: the actor's permissions are loaded on the first request only
    $few = $count();
    foreach (range(1, 5) as $_) {
        ledgerInvoice($customer, '2026-03-01', '100.00', [['2026-11-01', '50.00'], ['2026-12-01', '50.00']]);
    }

    expect($count())->toEqual($few);
});
