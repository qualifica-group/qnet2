<?php

use App\Enums\PurchaseOrderStatus as Status;
use App\Models\ExportRun;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function orderRows(object $test, array $state = []): array
{
    return $test->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 50] + $state)->assertOk()->json('items');
}

function orderWithTotals(array $attributes = [], Status $status = Status::Draft, string $total = '100.00'): PurchaseOrder
{
    $order = PurchaseOrder::factory()->withStatus($status)->create($attributes);
    $order->forceFill(['taxable_total' => $total, 'vat_total' => '22.00', 'grand_total' => number_format((float) $total + 22, 2, '.', '')])->save();

    return $order;
}

it('AC-015: the rows expose the contract columns and the row abilities', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update', 'delete', 'changeStatus', 'approve', 'viewActivity']));
    $order = purchaseOrderWithLines(Status::Draft, 2);
    $order->sectors()->attach(Sector::factory()->create(['name' => 'Energy'])->id);

    $row = orderRows($this)[0];

    expect(array_keys($row))->toContain(
        'id', 'code', 'number', 'year', 'order_date', 'status', 'supplier', 'supplier_vat_number', 'company', 'company_site',
        'operational_site', 'description', 'payment_method', 'customer', 'work_order', 'quote', 'opportunity', 'sectors',
        'is_internal_user', 'taxable_total', 'vat_total', 'grand_total', 'lines_count', 'created_by', 'created_at', 'abilities',
    )
        ->and($row['code'])->toBe($order->code())
        ->and($row['lines_count'])->toBe(2)
        ->and($row['sectors'][0]['name'])->toBe('Energy')
        ->and($row['abilities'])->toBe(['update' => true, 'delete' => true, 'transitions' => ['in_validation', 'approved', 'cancelled']])
        ->and($row['actions'])->toBe(['view', 'pdf', 'delete', 'activity']);
});

it('AC-015: an approved order is not editable or deletable and the transitions follow the permissions', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update', 'delete', 'changeStatus']));
    purchaseOrderWithLines(Status::Approved);

    expect(orderRows($this)[0]['abilities'])->toBe(['update' => false, 'delete' => false, 'transitions' => ['in_validation', 'sent_to_supplier', 'cancelled']]);
});

it('AC-015: an unknown column in filters or sort never reaches the query', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    purchaseOrderWithLines();

    $this->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['password' => ['filterType' => 'text', 'filter' => 'zzz']]])
        ->assertUnprocessable()->assertJsonValidationErrors('filterModel.password');
    $this->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'id; drop table users', 'sort' => 'asc']]])
        ->assertUnprocessable()->assertJsonValidationErrors('sortModel.0.colId');
});

it('AC-015: it sorts by code (year, then number) and by a related column', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $old = orderWithTotals(['year' => 2025, 'number' => 9, 'supplier_id' => Registry::factory()->supplier()->create(['name' => 'Zeta'])->id]);
    $new = orderWithTotals(['year' => 2026, 'number' => 1, 'supplier_id' => Registry::factory()->supplier()->create(['name' => 'Alfa'])->id]);

    expect(collect(orderRows($this, ['sortModel' => [['colId' => 'code', 'sort' => 'asc']]]))->pluck('id')->all())->toBe([$old->id, $new->id])
        ->and(collect(orderRows($this))->pluck('id')->all())->toBe([$new->id, $old->id])
        ->and(collect(orderRows($this, ['sortModel' => [['colId' => 'supplier', 'sort' => 'asc']]]))->pluck('id')->all())->toBe([$new->id, $old->id]);
});

it('AC-015: it filters by text, set, date and number', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $rent = orderWithTotals(['description' => 'Office rent', 'order_date' => '2026-03-10', 'supplier_id' => Registry::factory()->supplier()->create(['name' => 'Acme Srl'])->id], Status::Approved, '500.00');
    $other = orderWithTotals(['description' => 'Cleaning', 'order_date' => '2026-06-10', 'supplier_id' => Registry::factory()->supplier()->create(['name' => 'Beta Spa'])->id]);

    $ids = fn (array $filterModel): array => collect(orderRows($this, ['filterModel' => $filterModel]))->pluck('id')->all();

    expect($ids(['description' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'rent']]))->toBe([$rent->id])
        ->and($ids(['supplier' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Beta']]))->toBe([$other->id])
        ->and($ids(['supplier' => ['filterType' => 'set', 'values' => ['Acme Srl']]]))->toBe([$rent->id])
        ->and($ids(['status' => ['filterType' => 'set', 'values' => ['approved']]]))->toBe([$rent->id])
        ->and($ids(['order_date' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-05-01']]))->toBe([$other->id])
        ->and($ids(['grand_total' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 300]]))->toBe([$rent->id]);
});

it('AC-015: the status tab filters the orders and a value outside the allow-list is ignored', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $draft = purchaseOrderWithLines(Status::Draft);
    purchaseOrderWithLines(Status::Cancelled);

    $tab = orderRows($this, ['filterModel' => ['status' => ['filterType' => 'set', 'values' => ['draft']]]]);

    expect(collect($tab)->pluck('id')->all())->toBe([$draft->id])
        ->and(orderRows($this, ['filterModel' => ['status' => ['filterType' => 'set', 'values' => ['bogus']]]]))->toHaveCount(2);
});

it('AC-015: it filters by sector name', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $tagged = orderWithTotals();
    $tagged->sectors()->attach(Sector::factory()->create(['name' => 'Energy'])->id);
    orderWithTotals();

    $rows = orderRows($this, ['filterModel' => ['sectors' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Ener']]]);

    expect(collect($rows)->pluck('id')->all())->toBe([$tagged->id]);
});

it('AC-015: the quick search matches code, description, supplier name and supplier VAT number', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $supplier = Registry::factory()->supplier()->create(['name' => 'Forniture Spa']);
    $supplier->personalData()->create(['type' => 'company', 'company_name' => 'Forniture Spa', 'vat_number' => '01234567890']);
    $byVat = orderWithTotals(['supplier_id' => $supplier->id, 'year' => 2024, 'number' => 7, 'description' => 'Alpha']);
    $byDescription = orderWithTotals(['description' => 'Unique-needle', 'year' => 2025, 'number' => 30]);

    $search = fn (string $term): array => collect(orderRows($this, ['search' => $term]))->pluck('id')->all();

    expect($search('0123456'))->toBe([$byVat->id])
        ->and($search('Forniture'))->toBe([$byVat->id])
        ->and($search('needle'))->toBe([$byDescription->id])
        ->and($search('7/2024'))->toBe([$byVat->id])
        ->and($search('30'))->toBe([$byDescription->id])
        ->and($search('99/2000'))->toBe([]);
});

it('AC-015: the aggregates sum the filtered set, not the page', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    orderWithTotals(['description' => 'Keep one'], Status::Draft, '100.00');
    orderWithTotals(['description' => 'Keep two'], Status::Draft, '50.50');
    orderWithTotals(['description' => 'Drop'], Status::Cancelled, '900.00');

    $response = $this->postJson('/api/tables/purchase-orders/rows', [
        'startRow' => 0,
        'endRow' => 1,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['draft']]],
    ])->assertOk();

    expect($response->json('meta.aggregates'))->toBe(['taxable_total' => '150.50', 'vat_total' => '44.00', 'grand_total' => '194.50'])
        ->and($response->json('items'))->toHaveCount(1);
});

it('AC-015: the export runs for the filtered orders and needs the export permission', function () {
    Storage::fake('local');
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'export']));
    orderWithTotals(['description' => 'Exported order']);

    $response = $this->postJson('/api/exports/purchase-orders', [
        'format' => 'csv',
        'columns' => [['colId' => 'description', 'header' => 'Description']],
    ])->assertCreated();

    expect(Storage::disk('local')->get(ExportRun::findOrFail($response->json('data.export_run.id'))->file_path))->toContain('Exported order');

    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $this->postJson('/api/exports/purchase-orders', ['format' => 'csv', 'columns' => [['colId' => 'description', 'header' => 'Description']]])->assertForbidden();
});

it('AC-015: the grid needs the view permission', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['create']));

    $this->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->postJson('/api/tables/purchase-orders/values', ['columnId' => 'supplier'])->assertForbidden();
});

it('AC-015: the config lists the actions the actor may use', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'viewActivity']));

    $keys = collect($this->getJson('/api/tables/purchase-orders/columns')->assertOk()->json('data.actions'))->pluck('key')->all();

    expect($keys)->toBe(['view', 'pdf', 'activity']);
});

it('AC-015: every filterable column answers the rows and the distinct-values endpoints', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $order = purchaseOrderWithLines();
    $order->sectors()->attach(Sector::factory()->create(['name' => 'Energy'])->id);

    $filters = $this->getJson('/api/tables/purchase-orders/columns')->assertOk()->json('data.filters');
    expect($filters)->not->toBeEmpty();

    foreach ($filters as $filter) {
        $model = match ($filter['type']) {
            'set' => ['filterType' => 'set', 'values' => [$filter['options'][0] ?? 'x']],
            'number' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 0],
            'date' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2000-01-01'],
            'boolean' => ['filterType' => 'boolean', 'values' => [false]],
            default => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'a'],
        };

        $this->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 10, 'filterModel' => [$filter['columnId'] => $model]])->assertOk();
        $this->postJson('/api/tables/purchase-orders/values', ['columnId' => $filter['columnId']])->assertOk();
    }

    $supplierValues = $this->postJson('/api/tables/purchase-orders/values', ['columnId' => 'supplier'])->json('data.values');
    $sectorValues = $this->postJson('/api/tables/purchase-orders/values', ['columnId' => 'sectors'])->json('data.values');

    expect($supplierValues)->toBe([$order->supplier->name])
        ->and($sectorValues)->toBe(['Energy']);
});

it('AC-015: the column definitions load for a user with view (no persisted order to read a status from)', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update', 'delete', 'merge', 'send', 'export', 'viewActivity']));

    $columns = collect($this->getJson('/api/tables/purchase-orders/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($columns)->toContain('code', 'status', 'supplier', 'grand_total');
});
