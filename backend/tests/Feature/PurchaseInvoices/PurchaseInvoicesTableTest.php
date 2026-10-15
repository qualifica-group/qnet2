<?php

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

const PURCHASE_INVOICES_ROWS_URL = '/api/tables/purchase-invoices/rows';

if (! function_exists('listActor')) {
    /**
     * A user holding exactly the given purchase-invoices permissions.
     *
     * @param  array<int, string>  $abilities
     */
    function listActor(array $abilities): User
    {
        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("purchase-invoices.{$ability}", 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}

function listRows(array $body = []): array
{
    return test()->postJson(PURCHASE_INVOICES_ROWS_URL, $body + ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items');
}

const LIST_FULL_ABILITIES = ['viewAny', 'view', 'update', 'delete', 'viewActivity'];

it('AC-009: exposes the contract columns, filters and actions', function () {
    Sanctum::actingAs(listActor(LIST_FULL_ABILITIES));

    $data = $this->getJson('/api/tables/purchase-invoices/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns->keys()->all())->toBe([
        'id', 'document_type', 'document_number', 'document_date', 'supplier', 'company', 'purchase_orders',
        'total_amount', 'payable_amount', 'paid_amount', 'residual_amount', 'payment_status',
    ])
        ->and($columns['document_type']['filterType'])->toBe('set')
        ->and($columns['document_type']['options'])->toBe(['proforma', 'invoice'])
        ->and($columns['supplier']['filterType'])->toBe('set')
        ->and($columns['company']['filterType'])->toBe('set')
        ->and($columns['purchase_orders']['sortable'])->toBeFalse()
        // Spec 0262 (REQUIREMENT CHANGED): edit and details live on the record, opened by `view`.
        ->and(collect($data['actions'])->pluck('key')->all())->toBe(['view', 'delete', 'activity']);
});

it('AC-009: lists one row per document with total, paid, residual, linked orders and derived payment status', function () {
    Sanctum::actingAs(listActor(LIST_FULL_ABILITIES));
    $supplier = Registry::factory()->supplier()->create(['name' => 'Forniture Srl']);
    $partial = purchaseDocument($supplier, ['document_number' => 'F-1', 'total_amount' => '300.00']);
    purchaseInstallmentOf($partial, ['due_date' => '2026-04-14', 'amount' => '100.00', 'paid_amount' => '100.00', 'paid_at' => '2026-04-15']);
    purchaseInstallmentOf($partial, ['due_date' => '2026-05-14', 'amount' => '100.00']);
    purchaseInstallmentOf($partial, ['due_date' => '2026-06-13', 'amount' => '100.00']);
    $partial->purchaseOrders()->attach([
        PurchaseOrder::factory()->create(['number' => 5, 'year' => 2026])->id,
        PurchaseOrder::factory()->create(['number' => 2, 'year' => 2026])->id,
    ]);
    $paid = purchaseDocument($supplier, ['document_number' => 'F-2', 'total_amount' => '50.00']);
    purchaseInstallmentOf($paid, ['due_date' => '2026-04-14', 'amount' => '50.00', 'paid_amount' => '50.00', 'paid_at' => '2026-04-15']);
    $future = purchaseDocument($supplier, ['document_number' => 'F-3', 'total_amount' => '70.00']);
    purchaseInstallmentOf($future, ['due_date' => Carbon::today()->addMonth()->toDateString(), 'amount' => '70.00']);
    $unscheduled = purchaseDocument($supplier, ['document_number' => 'F-4', 'total_amount' => '10.00']);

    $rows = collect(listRows())->keyBy('id');

    expect($rows)->toHaveCount(4)
        ->and($rows[$partial->id])->toMatchArray([
            'document_type' => 'invoice', 'document_number' => 'F-1', 'supplier' => 'Forniture Srl', 'supplier_id' => $supplier->id,
            'company' => $partial->company->denomination, 'purchase_orders' => '2/2026, 5/2026',
            'total_amount' => '300.00', 'paid_amount' => '100.00', 'residual_amount' => '200.00', 'payment_status' => 'seriously_overdue',
        ])
        ->and($rows[$paid->id])->toMatchArray(['paid_amount' => '50.00', 'residual_amount' => '0.00', 'payment_status' => 'paid', 'purchase_orders' => null])
        ->and($rows[$future->id])->toMatchArray(['paid_amount' => '0.00', 'residual_amount' => '70.00', 'payment_status' => 'not_due'])
        ->and($rows[$unscheduled->id]['payment_status'])->toBe('not_due');
});

// Spec 0262 (REQUIREMENT CHANGED): edit and details moved to the record; delete keeps its rules.
it('AC-009: offers delete only on a proforma without payments', function () {
    Sanctum::actingAs(listActor(LIST_FULL_ABILITIES));
    $proforma = purchaseDocument(null, ['document_type' => 'proforma', 'document_number' => null]);
    $paidProforma = purchaseDocument(null, ['document_type' => 'proforma', 'document_number' => null]);
    purchaseInstallmentOf($paidProforma, ['amount' => '10.00', 'paid_amount' => '10.00', 'paid_at' => '2026-04-15']);
    $invoice = purchaseDocument();

    $rows = collect(listRows())->keyBy('id');

    expect($rows[$proforma->id]['actions'])->toBe(['view', 'delete', 'activity'])
        ->and($rows[$paidProforma->id]['actions'])->toBe(['view', 'activity'])
        ->and($rows[$invoice->id]['actions'])->toBe(['view', 'activity']);

    Sanctum::actingAs(listActor(['viewAny', 'view']));

    expect(collect(listRows())->pluck('actions')->unique()->all())->toBe([['view']]);
});

it('AC-009: filters by type, supplier and company and sorts by supplier server side', function () {
    Sanctum::actingAs(listActor(LIST_FULL_ABILITIES));
    $acme = Registry::factory()->supplier()->create(['name' => 'Acme']);
    $beta = Registry::factory()->supplier()->create(['name' => 'Beta']);
    $company = Company::factory()->create(['denomination' => 'Qualifica Spa']);
    $a = purchaseDocument($acme, ['document_type' => 'proforma', 'document_number' => null, 'company_id' => $company->id, 'document_date' => '2026-03-10']);
    $b = purchaseDocument($beta, ['document_number' => 'B-1', 'document_date' => '2026-03-20']);
    $ids = static fn (array $body): array => array_column(listRows($body), 'id');

    expect($ids([]))->toBe([$b->id, $a->id])
        ->and($ids(['sortModel' => [['colId' => 'supplier', 'sort' => 'desc']]]))->toBe([$b->id, $a->id])
        ->and($ids(['sortModel' => [['colId' => 'company', 'sort' => 'asc']]]))->toHaveCount(2)
        ->and($ids(['filterModel' => ['document_type' => ['filterType' => 'set', 'values' => ['proforma']]]]))->toBe([$a->id])
        ->and($ids(['filterModel' => ['supplier' => ['filterType' => 'set', 'values' => ['Beta']]]]))->toBe([$b->id])
        ->and($ids(['filterModel' => ['company' => ['filterType' => 'set', 'values' => ['Qualifica Spa']]]]))->toBe([$a->id])
        ->and($ids(['filterModel' => ['document_number' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'B-']]]))->toBe([$b->id]);

    $this->postJson(PURCHASE_INVOICES_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'purchase_orders', 'sort' => 'asc']]])->assertUnprocessable();
    $this->postJson(PURCHASE_INVOICES_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['supplier_id' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 1]]])->assertUnprocessable();
});

it('AC-009: the set filter lists the suppliers and companies of the documents', function () {
    Sanctum::actingAs(listActor(LIST_FULL_ABILITIES));
    purchaseDocument(Registry::factory()->supplier()->create(['name' => 'Acme']));
    purchaseDocument(Registry::factory()->supplier()->create(['name' => 'Beta']));
    Registry::factory()->supplier()->create(['name' => 'Unused']);

    $values = $this->postJson('/api/tables/purchase-invoices/values', ['columnId' => 'supplier'])->assertOk()->json('data.values');

    expect($values)->toBe(['Acme', 'Beta']);
});

it('AC-009: runs the same number of queries whatever the number of documents', function () {
    Sanctum::actingAs(listActor(LIST_FULL_ABILITIES));
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });
    // The first request also loads permissions and custom fields: warm up before measuring.
    $queries = function () use (&$count): int {
        $count = 0;
        listRows();

        return $count;
    };
    purchaseInstallmentOf(purchaseDocument(), ['amount' => '10.00']);
    $queries();
    $few = $queries();

    foreach (range(1, 5) as $_) {
        purchaseInstallmentOf(purchaseDocument(), ['amount' => '10.00']);
    }

    expect($queries())->toBe($few);
});

it('AC-009: a user without purchase-invoices.viewAny gets 403', function () {
    Sanctum::actingAs(listActor([]));

    $this->getJson('/api/tables/purchase-invoices/columns')->assertForbidden();
    $this->postJson(PURCHASE_INVOICES_ROWS_URL, ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('AC-009: the navigation lists Fatture passive before Scadenze passive, with purchase-invoices.view', function () {
    Sanctum::actingAs(listActor(['viewAny']));
    expect(json_encode($this->getJson('/api/navigation')->assertOk()->json()))->not->toContain('purchase-invoices"');

    $user = listActor(['view']);
    $user->givePermissionTo(Permission::findOrCreate('purchase-invoice-installments.view', 'web'));
    Sanctum::actingAs($user);
    $json = json_encode($this->getJson('/api/navigation')->assertOk()->json());

    expect(strpos($json, '"purchase-invoices"'))->toBeInt()->toBeLessThan(strpos($json, '"purchase-invoice-installments"'));
});

it('AC-009: the installments grid offers open_document only with purchase-invoices.view', function () {
    $installment = purchaseInstallmentOf(purchaseDocument(), ['amount' => '10.00']);

    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view'], ['purchase-invoices.view']));
    $row = collect($this->postJson('/api/tables/purchase-invoice-installments/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))->firstWhere('id', $installment->id);
    $actions = $this->getJson('/api/tables/purchase-invoice-installments/columns')->assertOk()->json('data.actions');

    expect($row['actions'])->toBe(['open_document'])
        ->and(collect($actions)->firstWhere('key', 'open_document'))->toMatchArray(['type' => 'link', 'icon' => 'file-text']);

    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view'], ['purchase-invoices.pay']));
    $row = collect($this->postJson('/api/tables/purchase-invoice-installments/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))->firstWhere('id', $installment->id);

    expect($row['actions'])->toBe(['record_payment']);
});
