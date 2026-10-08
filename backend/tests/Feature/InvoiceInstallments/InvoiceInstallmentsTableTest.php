<?php

use App\Models\CompanySite;
use App\Models\ExportRun;
use App\Models\OperationalSite;
use App\Models\Registry;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function installmentRows(array $body = []): array
{
    return test()->postJson('/api/tables/invoice-installments/rows', $body + ['startRow' => 0, 'endRow' => 50])->assertOk()->json();
}

function viewer(): void
{
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view'], ['invoices.view', 'invoices.collect']));
}

it('AC-001: exposes the contract columns with their sort/filter flags and the row grouping config', function () {
    viewer();

    $data = $this->getJson('/api/tables/invoice-installments/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns->keys()->all())->toBe([
        'id', 'invoice_number_label', 'invoice_document_date', 'sequence', 'due_date', 'due_month', 'days_overdue', 'status',
        'overdue', 'customer', 'work_order', 'company', 'company_site', 'operational_site', 'payment_method_code',
        'amount', 'collected_amount', 'residual_amount', 'collected_at',
    ])
        ->and($data['row_grouping'])->toBe([
            'enabled' => true,
            'max_depth' => 3,
            'columns' => ['customer', 'work_order', 'company_site', 'operational_site', 'company', 'payment_method_code', 'due_month'],
        ])
        ->and(collect($data['columns'])->where('groupable', true)->pluck('id')->sort()->values()->all())
        ->toBe(collect($data['row_grouping']['columns'])->sort()->values()->all())
        ->and($columns['amount']['aggFunc'])->toBe('sum')
        ->and($columns['residual_amount']['aggFunc'])->toBe('sum')
        ->and($columns['customer']['aggFunc'])->toBeNull()
        ->and($columns['status']['filterType'])->toBe('set')
        ->and($columns['status']['options'])->toBe(['unpaid', 'partially_paid', 'paid'])
        ->and($columns['overdue']['sortable'])->toBeFalse()
        ->and($columns['due_month']['sortable'])->toBeFalse()
        ->and($columns['days_overdue']['filterType'])->toBe('number')
        ->and(collect($data['actions'])->pluck('key')->all())->toBe(['view_invoice', 'record_collection', 'clear_collection']);
});

it('AC-001: rows carry the contract fields, the customer, work order and both sites', function () {
    viewer();
    $customer = Registry::factory()->create(['name' => 'Acme Spa']);
    $site = CompanySite::factory()->create(['name' => 'HQ Milano']);
    $operational = OperationalSite::factory()->create(['alias' => 'Plant 1']);
    $invoice = installmentInvoice($customer, $site, $operational, ['number' => 7, 'year' => 2026, 'document_date' => '2026-03-15']);
    $installment = installmentOf($invoice, ['due_date' => '2026-04-14', 'amount' => '100.00', 'payment_method_code' => 'RB30']);

    $row = collect(installmentRows()['items'])->firstWhere('id', $installment->id);

    expect($row)->toMatchArray([
        'invoice_id' => $invoice->id, 'invoice_number_label' => '7/2026', 'invoice_document_date' => '2026-03-15',
        'due_date' => '2026-04-14', 'due_month' => '2026-04', 'status' => 'unpaid', 'customer' => 'Acme Spa',
        'company_site' => 'HQ Milano', 'operational_site' => 'Plant 1', 'payment_method_code' => 'RB30',
        'amount' => '100.00', 'residual_amount' => '100.00', 'collected_at' => null,
    ])
        ->and($row['work_order'])->toBe($invoice->workOrder->code.' - '.$invoice->workOrder->title)
        ->and($row['actions'])->toBe(['view_invoice', 'record_collection']);
});

it('AC-002: a user without invoice-installments.view gets 403 on columns, rows and show', function () {
    Sanctum::actingAs(installmentUserWith([]));
    $installment = installmentOf(installmentInvoice());

    $this->getJson('/api/tables/invoice-installments/columns')->assertForbidden();
    $this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->getJson("/api/invoice-installments/{$installment->id}")->assertForbidden();
});

it('AC-002: the navigation entry shows only with invoice-installments.view', function () {
    Sanctum::actingAs(installmentUserWith([], ['invoices.view']));
    expect(json_encode($this->getJson('/api/navigation')->assertOk()->json()))->not->toContain('invoice-installments');

    Sanctum::actingAs(installmentUserWith(['view']));
    $json = json_encode($this->getJson('/api/navigation')->assertOk()->json());
    expect($json)->toContain('invoice-installments')->toContain('/invoice-installments')->toContain('calendar-clock');
});

it('AC-004: sorts and filters server side on every declared column and rejects an unknown colId with 422', function () {
    viewer();
    $acme = installmentInvoice(Registry::factory()->create(['name' => 'Acme']));
    $beta = installmentInvoice(Registry::factory()->create(['name' => 'Beta']));
    $a = installmentOf($acme, ['due_date' => '2026-05-01', 'amount' => '300.00']);
    $b = installmentOf($beta, ['due_date' => '2026-04-01', 'amount' => '100.00', 'collected_amount' => '100.00', 'collected_at' => '2026-04-02']);
    $c = installmentOf($beta, ['due_date' => '2026-06-01', 'amount' => '200.00', 'collected_amount' => '50.00', 'collected_at' => '2026-04-02']);
    $ids = static fn (array $body): array => array_column(installmentRows($body)['items'], 'id');

    foreach (['invoice_number_label', 'invoice_document_date', 'sequence', 'due_date', 'days_overdue', 'status', 'customer', 'work_order', 'company', 'company_site', 'operational_site', 'payment_method_code', 'amount', 'collected_amount', 'residual_amount', 'collected_at'] as $column) {
        expect($ids(['sortModel' => [['colId' => $column, 'sort' => 'desc']]]))->toHaveCount(3);
    }

    expect($ids(['sortModel' => [['colId' => 'due_date', 'sort' => 'asc']]]))->toBe([$b->id, $a->id, $c->id])
        ->and($ids(['sortModel' => [['colId' => 'amount', 'sort' => 'desc']]]))->toBe([$a->id, $c->id, $b->id])
        ->and($ids(['sortModel' => [['colId' => 'status', 'sort' => 'desc']]]))->toBe([$b->id, $c->id, $a->id])
        ->and($ids(['sortModel' => [['colId' => 'customer', 'sort' => 'asc'], ['colId' => 'due_date', 'sort' => 'desc']]]))->toBe([$a->id, $c->id, $b->id])
        ->and($ids(['filterModel' => ['status' => ['filterType' => 'set', 'values' => ['paid', 'partially_paid']]]]))->toEqualCanonicalizing([$b->id, $c->id])
        ->and($ids(['filterModel' => ['customer' => ['filterType' => 'set', 'values' => ['Acme']]]]))->toBe([$a->id])
        ->and($ids(['filterModel' => ['amount' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 150]]]))->toEqualCanonicalizing([$a->id, $c->id])
        ->and($ids(['filterModel' => ['due_date' => ['filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2026-04-15', 'dateTo' => '2026-05-15']]]))->toBe([$a->id])
        ->and($ids(['filterModel' => ['due_month' => ['filterType' => 'set', 'values' => ['2026-06']]]]))->toBe([$c->id])
        ->and($ids(['filterModel' => ['invoice_number_label' => ['filterType' => 'text', 'type' => 'contains', 'filter' => (string) $acme->number]]]))->toContain($a->id);

    $this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'invoice_id', 'sort' => 'asc']]])->assertUnprocessable();
    $this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['invoice_id' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 1]]])->assertUnprocessable();
});

it('AC-016: overdue and days_overdue follow the derived status', function () {
    viewer();
    $invoice = installmentInvoice();
    $late = installmentOf($invoice, ['due_date' => Carbon::today()->subDay()->toDateString(), 'amount' => '10.00']);
    $settled = installmentOf($invoice, ['due_date' => Carbon::today()->subDays(5)->toDateString(), 'amount' => '10.00', 'collected_amount' => '10.00']);
    $future = installmentOf($invoice, ['due_date' => Carbon::today()->addDay()->toDateString(), 'amount' => '10.00']);
    $partialLate = installmentOf($invoice, ['due_date' => Carbon::today()->subDays(3)->toDateString(), 'amount' => '10.00', 'collected_amount' => '4.00']);

    $rows = collect(installmentRows()['items'])->keyBy('id');

    expect($rows[$late->id])->toMatchArray(['overdue' => 'yes', 'days_overdue' => 1])
        ->and($rows[$settled->id])->toMatchArray(['overdue' => 'no', 'days_overdue' => 0])
        ->and($rows[$future->id])->toMatchArray(['overdue' => 'no', 'days_overdue' => 0])
        ->and($rows[$partialLate->id])->toMatchArray(['overdue' => 'yes', 'days_overdue' => 3]);

    $ids = static fn (array $filter): array => array_column(installmentRows(['filterModel' => $filter])['items'], 'id');

    expect($ids(['overdue' => ['filterType' => 'set', 'values' => ['yes']]]))->toEqualCanonicalizing([$late->id, $partialLate->id])
        ->and($ids(['overdue' => ['filterType' => 'set', 'values' => ['no']]]))->toEqualCanonicalizing([$settled->id, $future->id])
        ->and($ids(['days_overdue' => ['filterType' => 'number', 'type' => 'greaterThanOrEqual', 'filter' => 3]]))->toBe([$partialLate->id])
        ->and($ids(['days_overdue' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 0]]))->toEqualCanonicalizing([$settled->id, $future->id]);

    $sorted = array_column(installmentRows(['sortModel' => [['colId' => 'days_overdue', 'sort' => 'desc']]])['items'], 'id');
    expect(array_slice($sorted, 0, 2))->toBe([$partialLate->id, $late->id]);
});

it('returns the footer aggregates over the whole filtered set and the distinct values of the set columns', function () {
    viewer();
    $invoice = installmentInvoice(Registry::factory()->create(['name' => 'Acme']));
    installmentOf($invoice, ['amount' => '100.00', 'collected_amount' => '30.00']);
    installmentOf($invoice, ['amount' => '50.50']);

    $body = installmentRows(['startRow' => 0, 'endRow' => 1]);
    expect($body['meta']['aggregates'])->toBe(['amount' => '150.50', 'collected_amount' => '30.00', 'residual_amount' => '120.50']);

    $values = $this->postJson('/api/tables/invoice-installments/values', ['columnId' => 'customer', 'filterModel' => []])->assertOk()->json('data.values');
    expect($values)->toContain('Acme');

    $status = $this->postJson('/api/tables/invoice-installments/values', ['columnId' => 'status', 'filterModel' => []])->assertOk()->json('data.values');
    expect($status)->toBe(['unpaid', 'partially_paid', 'paid']);
});

it('AC-014: a role that cannot see the amounts never receives them in config, rows, aggregates, groups or export, and cannot sort, filter or group on them', function () {
    $user = installmentUserWith(['viewAny', 'view', 'export']);
    $role = Role::create(['name' => 'no-amounts']);
    $user->assignRole($role);
    foreach (['amount', 'collected_amount', 'residual_amount', 'collected_at'] as $field) {
        $role->fieldPermissions()->create(['resource' => 'invoice-installments', 'field' => $field, 'visible' => false, 'editable' => false, 'required' => false]);
    }
    Sanctum::actingAs($user);
    installmentOf(installmentInvoice(), ['amount' => '100.00']);

    $data = $this->getJson('/api/tables/invoice-installments/columns')->assertOk()->json('data');
    expect(array_column($data['columns'], 'id'))->not->toContain('amount', 'collected_amount', 'residual_amount', 'collected_at')
        ->and($data['row_grouping']['columns'])->toContain('customer')
        ->and(collect($data['columns'])->pluck('aggFunc')->filter()->all())->toBe([]);

    $body = installmentRows();
    expect($body['items'][0])->not->toHaveKeys(['amount', 'collected_amount', 'residual_amount', 'collected_at'])
        ->and($body)->not->toHaveKey('meta');

    $group = installmentRows(['rowGroupCols' => ['customer'], 'groupKeys' => []]);
    expect($group['items'][0]['aggregates'])->toBe([])->and($group)->not->toHaveKey('meta');

    $this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'amount', 'sort' => 'asc']]])->assertUnprocessable();
    $this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['residual_amount' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 1]]])->assertUnprocessable();
    $this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 10, 'rowGroupCols' => ['amount']])->assertUnprocessable();
    $this->postJson('/api/exports/invoice-installments', ['format' => 'csv', 'columns' => [['colId' => 'amount', 'header' => 'Amount']]])->assertUnprocessable();
});

it('AC-015: exports the filtered installments as csv and refuses without invoice-installments.export', function () {
    Storage::fake((string) config('exports.disk'));
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view', 'export']));
    $invoice = installmentInvoice(Registry::factory()->create(['name' => 'Acme']));
    installmentOf($invoice, ['amount' => '100.00', 'payment_method_code' => 'RB30']);
    installmentOf($invoice, ['amount' => '55.00', 'payment_method_code' => 'BB60', 'collected_amount' => '55.00']);

    $response = $this->postJson('/api/exports/invoice-installments', [
        'format' => 'csv',
        'columns' => [['colId' => 'customer', 'header' => 'Customer'], ['colId' => 'amount', 'header' => 'Amount']],
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['unpaid']]],
    ]);

    $response->assertSuccessful();
    $run = ExportRun::query()->latest('id')->firstOrFail();
    expect($run->row_count)->toBe(1);

    Sanctum::actingAs(installmentUserWith(['viewAny', 'view']));
    $this->postJson('/api/exports/invoice-installments', ['format' => 'csv', 'columns' => [['colId' => 'customer', 'header' => 'Customer']]])->assertForbidden();
});
