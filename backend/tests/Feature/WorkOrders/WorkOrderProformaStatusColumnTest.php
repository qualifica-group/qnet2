<?php

use App\Models\ProformaRequest;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * Spec 0407: the "€" column of the Commesse grid (legacy `OrdersTable` "€"),
 * the proforma state of each commessa, filterable and sortable, only for the
 * actors who may raise a proforma request.
 */

/**
 * Three commesse: no request, a pending one (beside an issued one) and an issued one.
 *
 * @return array{none: WorkOrder, pending: WorkOrder, issued: WorkOrder}
 */
function proformaStatusFixture(): array
{
    $none = WorkOrder::factory()->create();
    $pending = WorkOrder::factory()->create();
    $issued = WorkOrder::factory()->create();
    ProformaRequest::factory()->create(['work_order_id' => $pending->id]);
    ProformaRequest::factory()->issued()->create(['work_order_id' => $pending->id]);
    ProformaRequest::factory()->issued()->create(['work_order_id' => $issued->id]);

    return ['none' => $none, 'pending' => $pending, 'issued' => $issued];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<int, array<string, mixed>>
 */
function proformaStatusRows(array $body = []): array
{
    return test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25, ...$body])
        ->assertOk()
        ->json('items');
}

it('AC-001: rows carry none, pending or issued for an actor who may request a proforma', function () {
    Sanctum::actingAs(proformaUserWith(['create']));
    $orders = proformaStatusFixture();

    expect(collect(proformaStatusRows())->pluck('proforma_status', 'id')->all())->toEqualCanonicalizing([
        $orders['none']->id => 'none',
        $orders['pending']->id => 'pending',
        $orders['issued']->id => 'issued',
    ]);
});

it('AC-001: the column is declared as a sortable, set-filterable badge with its enum key', function () {
    Sanctum::actingAs(proformaUserWith(['create']));

    $column = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'proforma_status');

    expect($column)->not->toBeNull()
        ->and($column['label'])->toBe('workOrders.columns.proforma_status')
        ->and($column['type'])->toBe('badge')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterType'])->toBe('set')
        ->and($column['enumKey'])->toBe('work_order_proforma_status');
});

it('AC-002: the set filter keeps the commesse in the picked states', function (array $picked, array $expected) {
    Sanctum::actingAs(proformaUserWith(['create']));
    $orders = proformaStatusFixture();

    $ids = collect(proformaStatusRows([
        'filterModel' => ['proforma_status' => ['filterType' => 'set', 'values' => $picked]],
    ]))->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing(array_map(fn (string $key): int => $orders[$key]->id, $expected));
})->with([
    'none' => [['none'], ['none']],
    'pending' => [['pending'], ['pending']],
    'issued' => [['issued'], ['issued']],
    'none or issued' => [['none', 'issued'], ['none', 'issued']],
]);

it('AC-002: the filter offers the three states', function () {
    Sanctum::actingAs(proformaUserWith(['create']));

    $this->postJson('/api/tables/work-orders/values', ['columnId' => 'proforma_status', 'limit' => 25])
        ->assertOk()
        ->assertJsonPath('data.values', ['none', 'pending', 'issued']);
});

it('AC-003: sorting orders none, pending, issued and back', function (string $direction, array $expected) {
    Sanctum::actingAs(proformaUserWith(['create']));
    $orders = proformaStatusFixture();

    $ids = collect(proformaStatusRows(['sortModel' => [['colId' => 'proforma_status', 'sort' => $direction]]]))->pluck('id')->all();

    expect($ids)->toBe(array_map(fn (string $key): int => $orders[$key]->id, $expected));
})->with([
    'asc' => ['asc', ['none', 'pending', 'issued']],
    'desc' => ['desc', ['issued', 'pending', 'none']],
]);

it('AC-004: without proforma-requests.create the column, its value, filter and sort are absent', function () {
    Sanctum::actingAs(proformaUserWith(['view']));
    proformaStatusFixture();

    $columnIds = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($columnIds)->not->toContain('proforma_status')
        ->and(collect(proformaStatusRows())->every(fn (array $row): bool => ! array_key_exists('proforma_status', $row)))->toBeTrue();

    $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['proforma_status' => ['filterType' => 'set', 'values' => ['none']]],
    ])->assertUnprocessable();
    $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'proforma_status', 'sort' => 'asc']],
    ])->assertUnprocessable();
});
