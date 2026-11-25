<?php

use App\Enums\CommissionRecipientRole as Role;
use App\Enums\CommissionType;
use App\Enums\SupplierCommissionDirection;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
 * User request 2026-11-24: the Commesse grid gets the legacy `OrdersTable`
 * "Importo consulenza" (net of the Consulenza lines) and "Totale ricavo"
 * (effective revenue, the "Dati contrattuali" total), only for whoever sees
 * the contract data.
 */

/**
 * `mixed`: consultancy 1000 + 500, institution 2000 earning a 200 RECEIVED
 * Fornitore commission (a Commercial one never counts); `plain`: institution
 * 300 without direction; `empty`: no lines.
 *
 * @return array{mixed: WorkOrder, plain: WorkOrder, empty: WorkOrder}
 */
function amountColumnsFixture(): array
{
    $mixed = WorkOrder::factory()->create();
    contractDataLine($mixed, 'consultancy', 1000);
    contractDataLine($mixed, 'consultancy', 500);
    $institution = contractDataLine($mixed, 'institution', 2000, SupplierCommissionDirection::Received);
    contractDataCommission($institution, Role::Supplier, CommissionType::Percentage, 10, 200);
    contractDataCommission($institution, Role::Commercial, CommissionType::FixedAmount, 50, 50);

    $plain = WorkOrder::factory()->create();
    contractDataLine($plain, 'institution', 300);

    return ['mixed' => $mixed, 'plain' => $plain, 'empty' => WorkOrder::factory()->create()];
}

function amountColumnsUser(bool $withContractData = true): User
{
    $user = workOrderUserWith(['viewAny']);

    foreach (['viewContractData', 'viewCosts'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $user->givePermissionTo($withContractData ? ['work-orders.viewContractData', 'work-orders.viewCosts'] : ['work-orders.viewCosts']);

    return $user;
}

/**
 * @param  array<string, mixed>  $body
 * @return array<int, array<string, mixed>>
 */
function amountColumnsRows(array $body = []): array
{
    return test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25, ...$body])
        ->assertOk()
        ->json('items');
}

it('rows carry the consultancy net and the effective revenue of each commessa', function () {
    Sanctum::actingAs(amountColumnsUser());
    $orders = amountColumnsFixture();

    $rows = collect(amountColumnsRows())->keyBy('id');

    expect($rows[$orders['mixed']->id]['consultancy_amount'])->toBe('1500.00')
        ->and($rows[$orders['mixed']->id]['total_revenue'])->toBe('1700.00')
        ->and($rows[$orders['plain']->id]['consultancy_amount'])->toBe('0.00')
        ->and($rows[$orders['plain']->id]['total_revenue'])->toBe('300.00')
        ->and($rows[$orders['empty']->id]['consultancy_amount'])->toBe('0.00')
        ->and($rows[$orders['empty']->id]['total_revenue'])->toBe('0.00');
});

it('declares both columns as sortable number columns before the proforma column', function () {
    Sanctum::actingAs(amountColumnsUser());

    $columns = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))->keyBy('id');

    foreach (['consultancy_amount', 'total_revenue'] as $id) {
        expect($columns[$id]['label'])->toBe("workOrders.columns.{$id}")
            ->and($columns[$id]['type'])->toBe('number')
            ->and($columns[$id]['sortable'])->toBeTrue()
            ->and($columns[$id]['filterType'])->toBe('number');
    }
});

it('hides columns and values from whoever does not see the contract data', function () {
    Sanctum::actingAs(amountColumnsUser(withContractData: false));
    amountColumnsFixture();

    $ids = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))->pluck('id');
    $row = amountColumnsRows()[0];

    expect($ids)->not->toContain('consultancy_amount')
        ->and($ids)->not->toContain('total_revenue')
        ->and($row)->not->toHaveKey('consultancy_amount')
        ->and($row)->not->toHaveKey('total_revenue');
});

it('filters by amount with the same rule as the cell', function (string $columnId, array $filter, array $expected) {
    Sanctum::actingAs(amountColumnsUser());
    $orders = amountColumnsFixture();

    $ids = collect(amountColumnsRows(['filterModel' => [$columnId => ['filterType' => 'number', ...$filter]]]))->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing(array_map(fn (string $key): int => $orders[$key]->id, $expected));
})->with([
    'consultancy above 1000' => ['consultancy_amount', ['type' => 'greaterThan', 'filter' => 1000], ['mixed']],
    'consultancy equals 0' => ['consultancy_amount', ['type' => 'equals', 'filter' => 0], ['plain', 'empty']],
    'revenue equals 1700' => ['total_revenue', ['type' => 'equals', 'filter' => 1700], ['mixed']],
    'revenue between 100 and 500' => ['total_revenue', ['type' => 'inRange', 'filter' => 100, 'filterTo' => 500], ['plain']],
]);

it('sorts by amount with the same rule as the cell', function (string $columnId, string $direction, array $expected) {
    Sanctum::actingAs(amountColumnsUser());
    $orders = amountColumnsFixture();
    contractDataLine($orders['plain'], 'consultancy', 100);

    $ids = collect(amountColumnsRows(['sortModel' => [['colId' => $columnId, 'sort' => $direction]]]))->pluck('id')->all();

    expect($ids)->toBe(array_map(fn (string $key): int => $orders[$key]->id, $expected));
})->with([
    'consultancy desc' => ['consultancy_amount', 'desc', ['mixed', 'plain', 'empty']],
    'revenue asc' => ['total_revenue', 'asc', ['empty', 'plain', 'mixed']],
]);
