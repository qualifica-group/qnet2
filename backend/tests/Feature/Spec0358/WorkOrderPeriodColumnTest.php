<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0358, MT-05: the `renewal_number` ("Periodo") column of the
 * work-orders grid (D-14, AC-025).
 */
uses(RefreshDatabase::class);

function periodColumnActor(): User
{
    foreach (['work-orders.viewAny', 'work-orders.view', 'work-orders.viewAll'] as $name) {
        Permission::findOrCreate($name);
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['work-orders.viewAny', 'work-orders.view', 'work-orders.viewAll']);

    return $user;
}

/**
 * @return array{0: WorkOrder, 1: WorkOrder, 2: WorkOrder} commessa in period 0, period 1, without lines
 */
function periodColumnFixture(): array
{
    $contract = Contract::factory()->create();
    $initial = WorkOrder::factory()->create(['quote_id' => $contract->quote_id]);
    $renewed = WorkOrder::factory()->create(['quote_id' => $contract->quote_id]);
    $empty = WorkOrder::factory()->create(['quote_id' => $contract->quote_id]);
    attachLineInPeriod($initial, QuoteLine::factory()->create(['quote_id' => $contract->quote_id]), 0);
    attachLineInPeriod($renewed, QuoteLine::factory()->create(['quote_id' => $contract->quote_id]), 1);

    return [$initial, $renewed, $empty];
}

it('AC-025: the columns endpoint declares renewal_number as a read-only sortable number filter', function () {
    Sanctum::actingAs(periodColumnActor());

    $column = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'renewal_number');

    expect($column)->toMatchArray([
        'label' => 'workOrders.columns.renewal_number', 'type' => 'number', 'visible' => true,
        'sortable' => true, 'filterable' => true, 'filterType' => 'number',
    ])->and($column['editable'] ?? false)->toBeFalse();
});

it('AC-025: rows carry MAX(pivot.renewal_number), 0 without lines, and the number filter and sort work', function () {
    [$initial, $renewed, $empty] = periodColumnFixture();
    Sanctum::actingAs(periodColumnActor());

    $rows = fn (array $body): array => collect($this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, ...$body])->assertOk()->json('items'))->keyBy('id')->all();

    $all = $rows([]);
    expect($all[$initial->id]['renewal_number'])->toBe(0)
        ->and($all[$renewed->id]['renewal_number'])->toBe(1)
        ->and($all[$empty->id]['renewal_number'])->toBe(0);

    $filtered = $rows(['filterModel' => ['renewal_number' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 1]]]);
    expect(array_keys($filtered))->toBe([$renewed->id]);

    $zero = $rows(['filterModel' => ['renewal_number' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 0]]]);
    expect(array_keys($zero))->toEqualCanonicalizing([$initial->id, $empty->id]);

    $sorted = collect($this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, 'sortModel' => [['colId' => 'renewal_number', 'sort' => 'desc']]])->assertOk()->json('items'))->pluck('id')->all();
    expect($sorted[0])->toBe($renewed->id);
});

it('AC-025: in the contract tab (quoteId scope) the renewal commessa has value 1', function () {
    [$initial, $renewed] = periodColumnFixture();
    Sanctum::actingAs(periodColumnActor());

    $rows = collect($this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, 'quoteId' => $renewed->quote_id])->assertOk()->json('items'))->keyBy('id');

    expect($rows[$renewed->id]['renewal_number'])->toBe(1)
        ->and($rows[$initial->id]['renewal_number'])->toBe(0);
});
