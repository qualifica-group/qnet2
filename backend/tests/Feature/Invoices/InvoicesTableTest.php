<?php

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\ProformaRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function invoicesTableUserWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'collect'] as $ability) {
        Permission::findOrCreate("invoices.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("invoices.{$ability}");
    }

    return $user;
}

function invoiceOn(string $date, array $attributes = []): Invoice
{
    return Invoice::factory()->create($attributes + ['document_date' => $date, 'year' => (int) substr($date, 0, 4)]);
}

function invoiceRows(array $body = []): array
{
    return test()->postJson('/api/tables/invoices/rows', $body + ['startRow' => 0, 'endRow' => 50])->assertOk()->json();
}

it('AC-007: exposes the contract columns, the year/month filter columns and the action catalogue', function () {
    Sanctum::actingAs(invoicesTableUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']));

    $data = $this->getJson('/api/tables/invoices/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns->keys()->all())->toBe([
        'id', 'number_label', 'number', 'document_date', 'type', 'external_number', 'external_date', 'customer',
        'company', 'payment_method', 'work_order_code', 'quote_code', 'net_amount', 'vat_amount', 'total_amount',
        'collected_amount', 'residual_amount', 'payment_status', 'tag', 'deviation', 'document_year', 'document_month',
    ])
        ->and($columns['document_month']['filterType'])->toBe('set')
        ->and($columns['document_year']['filterable'])->toBeTrue()
        ->and($columns['document_year']['visible'])->toBeFalse()
        ->and(collect($data['actions'])->pluck('key')->all())->toBe(['view', 'update', 'details', 'delete', 'activity'])
        ->and(collect($data['actions'])->firstWhere('key', 'details')['icon'])->toBe('file-pen-line')
        ->and($data['defaultSort'])->toBe([
            ['columnId' => 'document_date', 'direction' => 'desc'],
            ['columnId' => 'number', 'direction' => 'desc'],
        ]);
});

it('AC-007: rows carry the contract fields, the collected/residual amounts and the payment status', function () {
    Sanctum::actingAs(invoicesTableUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']));
    $paid = invoiceOn('2026-01-10', ['number' => 1, 'net_amount' => '100.00', 'vat_amount' => '22.00', 'total_amount' => '122.00']);
    InvoiceInstallment::factory()->create(['invoice_id' => $paid->id, 'amount' => '122.00', 'collected_amount' => '122.00', 'collected_at' => '2026-02-01']);
    $notDue = invoiceOn('2026-01-11', ['number' => 2]);
    InvoiceInstallment::factory()->create(['invoice_id' => $notDue->id, 'due_date' => Carbon::today()->addDays(5)->toDateString()]);
    $overdue = invoiceOn('2026-01-12', ['number' => 3]);
    InvoiceInstallment::factory()->create(['invoice_id' => $overdue->id, 'due_date' => Carbon::today()->subDays(5)->toDateString()]);
    $serious = invoiceOn('2026-01-13', ['number' => 4]);
    InvoiceInstallment::factory()->create(['invoice_id' => $serious->id, 'sequence' => 1, 'amount' => '10.00', 'collected_amount' => '10.00', 'collected_at' => '2026-02-01']);
    InvoiceInstallment::factory()->create(['invoice_id' => $serious->id, 'sequence' => 2, 'due_date' => Carbon::today()->subDays(22)->toDateString()]);

    $rows = collect(invoiceRows()['items'])->keyBy('id');

    expect($rows[$paid->id])->toMatchArray([
        'number_label' => '1/2026', 'type' => 'proforma', 'collected_amount' => '122.00',
        'residual_amount' => '0.00', 'payment_status' => 'paid', 'total_amount' => '122.00',
    ])
        ->and(array_keys($rows[$paid->id]))->toContain(
            'number', 'document_date', 'external_number', 'external_date', 'customer', 'company', 'payment_method',
            'work_order_code', 'quote_code', 'net_amount', 'vat_amount', 'tag', 'deviation', 'actions',
        )
        ->and($rows[$paid->id]['actions'])->toBe(['view', 'update', 'details', 'delete', 'activity'])
        ->and($rows[$notDue->id]['payment_status'])->toBe('not_due')
        ->and($rows[$overdue->id]['payment_status'])->toBe('overdue')
        ->and($rows[$serious->id]['payment_status'])->toBe('seriously_overdue')
        ->and($rows[$serious->id]['collected_amount'])->toBe('10.00');
});

it('AC-007: document_month=[1,3] with document_year narrows, and aggregates equal the sum of the filtered rows', function () {
    Sanctum::actingAs(invoicesTableUserWith(['viewAny']));
    $jan = invoiceOn('2026-01-15', ['net_amount' => '100.00', 'vat_amount' => '22.00', 'total_amount' => '122.00']);
    $mar = invoiceOn('2026-03-02', ['net_amount' => '200.50', 'vat_amount' => '44.11', 'total_amount' => '244.61']);
    invoiceOn('2026-02-10');
    invoiceOn('2025-03-10');
    InvoiceInstallment::factory()->create(['invoice_id' => $jan->id, 'sequence' => 1, 'amount' => '60.00', 'collected_amount' => '60.00', 'collected_at' => '2026-02-01']);
    InvoiceInstallment::factory()->create(['invoice_id' => $mar->id, 'sequence' => 1, 'amount' => '244.61', 'collected_amount' => '100.00', 'collected_at' => '2026-03-20']);

    $result = invoiceRows(['filterModel' => [
        'document_month' => ['filterType' => 'set', 'values' => ['1', '3', '99', 'x']],
        'document_year' => ['filterType' => 'set', 'values' => ['2026']],
    ]]);

    expect(collect($result['items'])->pluck('id')->all())->toBe([$mar->id, $jan->id])
        ->and($result['meta']['aggregates'])->toBe([
            'net_amount' => '300.50',
            'vat_amount' => '66.11',
            'total_amount' => '366.61',
            'collected_amount' => '160.00',
            'residual_amount' => '206.61',
        ]);
});

it('AC-007: type, company, customer and number_label filters and the customer sort', function () {
    Sanctum::actingAs(invoicesTableUserWith(['viewAny']));
    $proforma = invoiceOn('2026-01-10', ['number' => 7, 'year' => 2026]);
    $invoice = Invoice::factory()->asInvoice()->create(['document_date' => '2026-02-10', 'year' => 2026, 'number' => 8]);

    $ids = fn (array $body) => collect(invoiceRows($body)['items'])->pluck('id')->all();

    expect($ids(['filterModel' => ['type' => ['filterType' => 'set', 'values' => ['invoice']]]]))->toBe([$invoice->id])
        ->and($ids(['filterModel' => ['company' => ['filterType' => 'set', 'values' => [$proforma->company->denomination]]]]))->toBe([$proforma->id])
        ->and($ids(['filterModel' => ['customer' => ['filterType' => 'text', 'type' => 'contains', 'filter' => $invoice->customer->name]]]))->toBe([$invoice->id])
        ->and($ids(['filterModel' => ['number_label' => ['filterType' => 'text', 'filter' => '7/2026']]]))->toBe([$proforma->id])
        ->and($ids(['filterModel' => ['number_label' => ['filterType' => 'text', 'filter' => 'abc']]]))->toBe([])
        ->and($ids(['sortModel' => [['colId' => 'customer', 'sort' => 'asc']]]))->toHaveCount(2)
        ->and($ids([]))->toBe([$invoice->id, $proforma->id]);
});

it('AC-007: the table is forbidden without invoices.viewAny', function () {
    Sanctum::actingAs(invoicesTableUserWith(['view']));

    $this->postJson('/api/tables/invoices/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

it('AC-007: proforma-requests rows expose invoice_id and the invoice action only with invoices.create', function () {
    $request = ProformaRequest::factory()->issued()->create();
    $open = ProformaRequest::factory()->create();
    $document = Invoice::factory()->create(['proforma_request_id' => $request->id]);
    $rows = fn () => collect(test()->postJson('/api/tables/proforma-requests/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))->keyBy('id');
    $actionKeys = fn () => collect(test()->getJson('/api/tables/proforma-requests/columns')->assertOk()->json('data.actions'))->pluck('key');

    $creator = proformaUserWith(['viewAny', 'view']);
    $creator->givePermissionTo(Permission::findOrCreate('invoices.create'));
    $plain = proformaUserWith(['viewAny', 'view']);
    Sanctum::actingAs($creator);

    expect($rows()[$request->id]['invoice_id'])->toBe($document->id)
        ->and($rows()[$open->id]['invoice_id'])->toBeNull()
        ->and($rows()[$open->id]['actions'])->toContain('invoice')
        ->and($actionKeys())->toContain('invoice')
        ->and(collect($this->getJson('/api/tables/proforma-requests/columns')->json('data.actions'))->firstWhere('key', 'invoice')['icon'])->toBe('file-plus-2');

    Sanctum::actingAs($plain);

    expect($rows()[$open->id]['actions'])->not->toContain('invoice')
        ->and($actionKeys())->not->toContain('invoice');
});
