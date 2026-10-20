<?php

use App\Models\Address;
use App\Models\CompanySite;
use App\Models\CostType;
use App\Models\ExportRun;
use App\Models\FinancialAccount;
use App\Models\OperationalSite;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\Role;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const PURCHASE_ROWS_URL = '/api/tables/purchase-invoice-installments/rows';

function purchaseRows(array $body = []): array
{
    return test()->postJson(PURCHASE_ROWS_URL, $body + ['startRow' => 0, 'endRow' => 50])->assertOk()->json();
}

function purchaseViewer(): void
{
    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view'], ['purchase-invoices.pay']));
}

it('AC-003: exposes the contract columns with their sort/filter flags and the row grouping config', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view', 'update'], ['purchase-invoices.pay']));

    $data = $this->getJson('/api/tables/purchase-invoice-installments/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns->keys()->all())->toBe([
        'id', 'document_type', 'document_number', 'document_date', 'purchase_orders', 'sequence', 'due_date', 'due_month',
        'days_overdue', 'status', 'overdue', 'supplier', 'cost_type', 'work_order', 'company', 'company_site',
        'operational_site', 'payment_method_code', 'amount', 'paid_amount', 'residual_amount', 'paid_at', 'financial_account',
    ])
        ->and($data['row_grouping'])->toBe([
            'enabled' => true,
            'max_depth' => 3,
            'columns' => ['supplier', 'cost_type', 'work_order', 'company', 'company_site', 'operational_site', 'payment_method_code', 'financial_account', 'due_month'],
        ])
        ->and(collect($data['columns'])->where('groupable', true)->pluck('id')->sort()->values()->all())
        ->toBe(collect($data['row_grouping']['columns'])->sort()->values()->all())
        ->and($columns['amount']['aggFunc'])->toBe('sum')
        ->and($columns['paid_amount']['aggFunc'])->toBe('sum')
        ->and($columns['residual_amount']['aggFunc'])->toBe('sum')
        ->and($columns['supplier']['aggFunc'])->toBeNull()
        ->and($columns['document_type']['options'])->toBe(['proforma', 'invoice'])
        ->and($columns['status']['filterType'])->toBe('set')
        ->and($columns['status']['options'])->toBe(['unpaid', 'paid'])
        ->and($columns['purchase_orders']['filterType'])->toBe('text')
        ->and($columns['purchase_orders']['sortable'])->toBeFalse()
        ->and($columns['overdue']['sortable'])->toBeFalse()
        ->and($columns['due_month']['sortable'])->toBeFalse()
        ->and($columns['days_overdue']['filterType'])->toBe('number')
        // Spec 0262 (REQUIREMENT CHANGED): due date and method are edited in the cells.
        ->and(collect($data['actions'])->pluck('key')->all())->toBe(['record_payment', 'clear_payment']);
});

it('AC-003: rows carry the contract fields, the supplier, work order, sites, linked orders and account', function () {
    purchaseViewer();
    $supplier = Registry::factory()->supplier()->create(['name' => 'Forniture Srl']);
    $site = CompanySite::factory()->create(['name' => 'HQ Milano']);
    $operational = OperationalSite::factory()->create(['alias' => 'Plant 1']);
    $account = FinancialAccount::factory()->create(['name' => 'Banca Uno']);
    $document = purchaseDocument($supplier, [
        'document_number' => 'F-77', 'document_date' => '2026-03-15', 'company_site_id' => $site->id, 'operational_site_id' => $operational->id,
        'cost_type_id' => CostType::query()->orderBy('id')->value('id'), 'work_order_id' => WorkOrder::factory()->create()->id,
    ]);
    $document->purchaseOrders()->attach([
        PurchaseOrder::factory()->create(['number' => 5, 'year' => 2026])->id,
        PurchaseOrder::factory()->create(['number' => 2, 'year' => 2026])->id,
    ]);
    $installment = purchaseInstallmentOf($document, ['due_date' => '2026-04-14', 'amount' => '100.00', 'payment_method_code' => 'RB30', 'financial_account_id' => $account->id]);

    $row = collect(purchaseRows()['items'])->firstWhere('id', $installment->id);

    expect($row)->toMatchArray([
        'purchase_invoice_id' => $document->id, 'supplier_id' => $supplier->id, 'document_type' => 'invoice', 'document_number' => 'F-77',
        'document_date' => '2026-03-15', 'purchase_orders' => '2/2026, 5/2026', 'sequence' => 1, 'due_date' => '2026-04-14', 'due_month' => '2026-04',
        'status' => 'unpaid', 'supplier' => 'Forniture Srl', 'company_site' => 'HQ Milano', 'operational_site' => 'Plant 1',
        'payment_method_code' => 'RB30', 'amount' => '100.00', 'paid_amount' => null, 'residual_amount' => '100.00',
        'paid_at' => null, 'financial_account' => 'Banca Uno', 'company' => $document->company->denomination,
        'cost_type' => ['name' => 'Collaboratori', 'color' => 'slate'],
    ])
        ->and($row['work_order'])->toBe($document->workOrder->code.' - '.$document->workOrder->title)
        ->and($row['actions'])->toBe(['record_payment']);
});

it('AC-003: a user without purchase-invoice-installments.view gets 403 on columns, rows and show', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith([]));
    $installment = purchaseInstallmentOf(purchaseDocument());

    $this->getJson('/api/tables/purchase-invoice-installments/columns')->assertForbidden();
    $this->postJson(PURCHASE_ROWS_URL, ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->getJson("/api/purchase-invoice-installments/{$installment->id}")->assertForbidden();
});

it('AC-003: the navigation entry shows only with purchase-invoice-installments.view', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith([], ['purchase-invoices.pay']));
    expect(json_encode($this->getJson('/api/navigation')->assertOk()->json()))->not->toContain('purchase-invoice-installments');

    Sanctum::actingAs(purchaseInstallmentUserWith(['view']));
    $json = json_encode($this->getJson('/api/navigation')->assertOk()->json());
    expect($json)->toContain('accounting-payable')->toContain('purchase-invoice-installments')
        ->toContain('purchase-invoice-installments')->toContain('calendar-clock');
});

it('AC-004: sorts and filters server side on every declared column and rejects an unknown colId with 422', function () {
    purchaseViewer();
    $acme = purchaseDocument(Registry::factory()->supplier()->create(['name' => 'Acme']), ['document_number' => 'A-1']);
    $beta = purchaseDocument(Registry::factory()->supplier()->create(['name' => 'Beta']), ['document_number' => 'B-1', 'document_date' => '2026-03-16']);
    $acme->purchaseOrders()->attach(PurchaseOrder::factory()->create(['number' => 11, 'year' => 2026])->id);
    $account = FinancialAccount::factory()->create(['name' => 'Cassa Uno']);
    $a = purchaseInstallmentOf($acme, ['due_date' => '2026-05-01', 'amount' => '300.00']);
    $b = purchaseInstallmentOf($beta, ['due_date' => '2026-04-01', 'amount' => '100.00', 'paid_amount' => '100.00', 'paid_at' => '2026-04-02', 'financial_account_id' => $account->id]);
    $c = purchaseInstallmentOf($beta, ['due_date' => '2026-06-01', 'amount' => '200.00', 'paid_amount' => '50.00', 'paid_at' => '2026-04-02']);
    $ids = static fn (array $body): array => array_column(purchaseRows($body)['items'], 'id');

    foreach (['document_type', 'document_number', 'document_date', 'sequence', 'due_date', 'days_overdue', 'status', 'supplier', 'cost_type', 'work_order', 'company', 'company_site', 'operational_site', 'payment_method_code', 'amount', 'paid_amount', 'residual_amount', 'paid_at', 'financial_account'] as $column) {
        expect($ids(['sortModel' => [['colId' => $column, 'sort' => 'desc']]]))->toHaveCount(3);
    }

    expect($ids([]))->toBe([$b->id, $a->id, $c->id])
        ->and($ids(['sortModel' => [['colId' => 'due_date', 'sort' => 'desc']]]))->toBe([$c->id, $a->id, $b->id])
        ->and($ids(['sortModel' => [['colId' => 'amount', 'sort' => 'desc']]]))->toBe([$a->id, $c->id, $b->id])
        ->and($ids(['sortModel' => [['colId' => 'status', 'sort' => 'desc']]]))->toBe([$b->id, $c->id, $a->id])
        ->and($ids(['sortModel' => [['colId' => 'supplier', 'sort' => 'asc'], ['colId' => 'due_date', 'sort' => 'desc']]]))->toBe([$a->id, $c->id, $b->id])
        ->and($ids(['filterModel' => ['status' => ['filterType' => 'set', 'values' => ['paid']]]]))->toEqualCanonicalizing([$b->id, $c->id])
        ->and($ids(['filterModel' => ['status' => ['filterType' => 'set', 'values' => ['unpaid']]]]))->toBe([$a->id])
        ->and($ids(['filterModel' => ['status' => ['filterType' => 'set', 'values' => ['partially_paid']]]]))->toHaveCount(3)
        ->and($ids(['filterModel' => ['supplier' => ['filterType' => 'set', 'values' => ['Acme']]]]))->toBe([$a->id])
        ->and($ids(['filterModel' => ['document_type' => ['filterType' => 'set', 'values' => ['proforma']]]]))->toBe([])
        ->and($ids(['filterModel' => ['document_number' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'B-']]]))->toEqualCanonicalizing([$b->id, $c->id])
        ->and($ids(['filterModel' => ['document_date' => ['filterType' => 'date', 'type' => 'equals', 'dateFrom' => '2026-03-16']]]))->toEqualCanonicalizing([$b->id, $c->id])
        ->and($ids(['filterModel' => ['purchase_orders' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '11/2026']]]))->toBe([$a->id])
        ->and($ids(['filterModel' => ['purchase_orders' => ['filterType' => 'text', 'type' => 'notContains', 'filter' => '11/2026']]]))->toEqualCanonicalizing([$b->id, $c->id])
        ->and($ids(['filterModel' => ['financial_account' => ['filterType' => 'set', 'values' => ['Cassa Uno']]]]))->toBe([$b->id])
        ->and($ids(['filterModel' => ['amount' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 150]]]))->toEqualCanonicalizing([$a->id, $c->id])
        ->and($ids(['filterModel' => ['due_date' => ['filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2026-04-15', 'dateTo' => '2026-05-15']]]))->toBe([$a->id])
        ->and($ids(['filterModel' => ['due_month' => ['filterType' => 'set', 'values' => ['2026-06']]]]))->toBe([$c->id]);

    $this->postJson(PURCHASE_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'purchase_invoice_id', 'sort' => 'asc']]])->assertUnprocessable();
    $this->postJson(PURCHASE_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'purchase_orders', 'sort' => 'asc']]])->assertUnprocessable();
    $this->postJson(PURCHASE_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['purchase_invoice_id' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 1]]])->assertUnprocessable();
});

it('AC-004: overdue and days_overdue follow the derived status (any payment closes the installment)', function () {
    purchaseViewer();
    $document = purchaseDocument();
    $late = purchaseInstallmentOf($document, ['due_date' => Carbon::today()->subDay()->toDateString(), 'amount' => '10.00']);
    $settled = purchaseInstallmentOf($document, ['due_date' => Carbon::today()->subDays(5)->toDateString(), 'amount' => '10.00', 'paid_amount' => '10.00']);
    $future = purchaseInstallmentOf($document, ['due_date' => Carbon::today()->addDay()->toDateString(), 'amount' => '10.00']);
    $paidLate = purchaseInstallmentOf($document, ['due_date' => Carbon::today()->subDays(3)->toDateString(), 'amount' => '10.00', 'paid_amount' => '4.00']);

    $rows = collect(purchaseRows()['items'])->keyBy('id');

    expect($rows[$late->id])->toMatchArray(['overdue' => 'yes', 'days_overdue' => 1])
        ->and($rows[$settled->id])->toMatchArray(['overdue' => 'no', 'days_overdue' => 0])
        ->and($rows[$future->id])->toMatchArray(['overdue' => 'no', 'days_overdue' => 0])
        ->and($rows[$paidLate->id])->toMatchArray(['overdue' => 'no', 'days_overdue' => 0]);

    $ids = static fn (array $filter): array => array_column(purchaseRows(['filterModel' => $filter])['items'], 'id');

    expect($ids(['overdue' => ['filterType' => 'set', 'values' => ['yes']]]))->toBe([$late->id])
        ->and($ids(['overdue' => ['filterType' => 'set', 'values' => ['no']]]))->toEqualCanonicalizing([$settled->id, $future->id, $paidLate->id])
        ->and($ids(['days_overdue' => ['filterType' => 'number', 'type' => 'greaterThanOrEqual', 'filter' => 1]]))->toBe([$late->id])
        ->and($ids(['days_overdue' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 0]]))->toEqualCanonicalizing([$settled->id, $future->id, $paidLate->id]);

    $sorted = array_column(purchaseRows(['sortModel' => [['colId' => 'days_overdue', 'sort' => 'desc']]])['items'], 'id');
    expect(array_slice($sorted, 0, 1))->toBe([$late->id]);
});

it('returns the footer aggregates over the whole filtered set and the distinct values of the set columns', function () {
    purchaseViewer();
    $document = purchaseDocument(Registry::factory()->supplier()->create(['name' => 'Acme']));
    purchaseInstallmentOf($document, ['amount' => '100.00', 'paid_amount' => '30.00']);
    purchaseInstallmentOf($document, ['amount' => '50.50']);

    $body = purchaseRows(['startRow' => 0, 'endRow' => 1]);
    expect($body['meta']['aggregates'])->toBe(['amount' => '150.50', 'paid_amount' => '30.00', 'residual_amount' => '120.50']);

    $values = $this->postJson('/api/tables/purchase-invoice-installments/values', ['columnId' => 'supplier', 'filterModel' => []])->assertOk()->json('data.values');
    expect($values)->toContain('Acme');

    $status = $this->postJson('/api/tables/purchase-invoice-installments/values', ['columnId' => 'status', 'filterModel' => []])->assertOk()->json('data.values');
    expect($status)->toBe(['unpaid', 'paid']);
});

it('AC-011: a role that cannot see amounts, payments or account never receives them in config, rows, aggregates, groups or export, and cannot sort, filter or group on them', function () {
    $user = purchaseInstallmentUserWith(['viewAny', 'view', 'export']);
    $role = Role::create(['name' => 'no-amounts']);
    $user->assignRole($role);
    $hidden = ['amount', 'paid_amount', 'paid_at', 'residual_amount', 'financial_account'];
    foreach ($hidden as $field) {
        $role->fieldPermissions()->create(['resource' => 'purchase-invoice-installments', 'field' => $field, 'visible' => false, 'editable' => false, 'required' => false]);
    }
    Sanctum::actingAs($user);
    $installment = purchaseInstallmentOf(purchaseDocument(), ['amount' => '100.00']);

    $data = $this->getJson('/api/tables/purchase-invoice-installments/columns')->assertOk()->json('data');
    expect(array_column($data['columns'], 'id'))->not->toContain(...$hidden)
        ->and($data['row_grouping']['columns'])->toContain('supplier')->not->toContain('financial_account')
        ->and(collect($data['columns'])->pluck('aggFunc')->filter()->all())->toBe([]);

    $body = purchaseRows();
    expect($body['items'][0])->not->toHaveKeys($hidden)
        ->and($body)->not->toHaveKey('meta');

    $group = purchaseRows(['rowGroupCols' => ['supplier'], 'groupKeys' => []]);
    expect($group['items'][0]['aggregates'])->toBe([])->and($group)->not->toHaveKey('meta');

    $show = $this->getJson("/api/purchase-invoice-installments/{$installment->id}")->assertOk()->json('data');
    expect($show)->not->toHaveKeys($hidden)
        ->and($show['purchase_invoice'])->not->toHaveKeys(['total_amount', 'financial_account'])
        ->and($show['purchase_invoice']['installments'][0])->not->toHaveKeys(['amount', 'paid_amount'])
        ->and($show['field_permissions']['amount'])->toBe(['visible' => false, 'editable' => false, 'required' => false]);

    $this->postJson(PURCHASE_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'amount', 'sort' => 'asc']]])->assertUnprocessable();
    $this->postJson(PURCHASE_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['residual_amount' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 1]]])->assertUnprocessable();
    $this->postJson(PURCHASE_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['financial_account' => ['filterType' => 'set', 'values' => ['x']]]])->assertUnprocessable();
    $this->postJson(PURCHASE_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'rowGroupCols' => ['financial_account']])->assertUnprocessable();
    $this->postJson('/api/exports/purchase-invoice-installments', ['format' => 'csv', 'columns' => [['colId' => 'amount', 'header' => 'Amount']]])->assertUnprocessable();
});

it('AC-012: exports the filtered installments as csv and refuses without purchase-invoice-installments.export', function () {
    Storage::fake((string) config('exports.disk'));
    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view', 'export']));
    $document = purchaseDocument(Registry::factory()->supplier()->create(['name' => 'Acme']));
    purchaseInstallmentOf($document, ['amount' => '100.00', 'payment_method_code' => 'RB30']);
    purchaseInstallmentOf($document, ['amount' => '55.00', 'payment_method_code' => 'BB60', 'paid_amount' => '55.00']);

    $this->postJson('/api/exports/purchase-invoice-installments', [
        'format' => 'csv',
        'columns' => [['colId' => 'supplier', 'header' => 'Supplier'], ['colId' => 'amount', 'header' => 'Amount']],
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['unpaid']]],
    ])->assertSuccessful();

    expect(ExportRun::query()->latest('id')->firstOrFail()->row_count)->toBe(1);

    Queue::fake();
    $this->postJson('/api/exports/purchase-invoice-installments', [
        'format' => 'xlsx',
        'columns' => [['colId' => 'supplier', 'header' => 'Supplier']],
    ])->assertSuccessful();

    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view']));
    $this->postJson('/api/exports/purchase-invoice-installments', ['format' => 'csv', 'columns' => [['colId' => 'supplier', 'header' => 'Supplier']]])->assertForbidden();
});

it('AC-005 (spec 0249): the cost_type cell carries name and color, null without a type, and the set filter stays by name', function () {
    purchaseViewer();
    $costType = CostType::factory()->create(['name' => 'Trasferte', 'color' => 'teal']);
    $typed = purchaseInstallmentOf(purchaseDocument(null, ['cost_type_id' => $costType->id]));
    $untyped = purchaseInstallmentOf(purchaseDocument(null, ['cost_type_id' => null]));

    $rows = collect(purchaseRows()['items'])->keyBy('id');
    $filtered = purchaseRows(['filterModel' => ['cost_type' => ['filterType' => 'set', 'values' => ['Trasferte']]]]);

    expect($rows[$typed->id]['cost_type'])->toBe(['name' => 'Trasferte', 'color' => 'teal'])
        ->and($rows[$untyped->id]['cost_type'])->toBeNull()
        ->and(collect($filtered['items'])->pluck('id')->all())->toBe([$typed->id]);
});

it('offers no value list on the linked orders and days overdue columns, whose values are composed', function () {
    purchaseViewer();
    purchaseInstallmentOf(purchaseDocument());

    $columns = collect($this->getJson('/api/tables/purchase-invoice-installments/columns')->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['purchase_orders']['hasFilterValues'])->toBeFalse()
        ->and($columns['days_overdue']['hasFilterValues'])->toBeFalse()
        ->and($columns['document_number']['hasFilterValues'])->toBeTrue();

    foreach (['purchase_orders', 'days_overdue'] as $column) {
        $this->postJson('/api/tables/purchase-invoice-installments/values', ['columnId' => $column, 'filterModel' => []])->assertOk()->assertJsonPath('data.values', []);
    }
});

it('labels an operational site without alias by its primary address and reads a paid amount never recorded as 0', function () {
    purchaseViewer();
    $site = OperationalSite::factory()->create(['alias' => null]);
    Address::factory()->primary()->for($site, 'addressable')->create(['line1' => 'Via Roma 1']);
    $open = purchaseInstallmentOf(purchaseDocument(null, ['operational_site_id' => $site->id]), ['amount' => '100.00']);
    $paid = purchaseInstallmentOf(purchaseDocument(), ['amount' => '100.00', 'paid_amount' => '100.00', 'paid_at' => '2026-04-02']);
    $ids = static fn (array $filter): array => array_column(purchaseRows(['filterModel' => $filter])['items'], 'id');

    expect(collect(purchaseRows()['items'])->firstWhere('id', $open->id)['operational_site'])->toBe('Via Roma 1')
        ->and($ids(['operational_site' => ['filterType' => 'set', 'values' => ['Via Roma 1']]]))->toBe([$open->id])
        ->and($ids(['paid_amount' => ['filterType' => 'multi', 'filterModels' => [['filterType' => 'number', 'type' => 'equals', 'filter' => 0], null]]]))->toBe([$open->id])
        ->and($ids(['paid_amount' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 0]]))->toBe([$paid->id]);
});
