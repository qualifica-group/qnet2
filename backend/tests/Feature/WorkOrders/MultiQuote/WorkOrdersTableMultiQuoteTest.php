<?php

use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function multiQuoteRows(array $body = []): array
{
    return test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25, ...$body])
        ->assertOk()->json('items');
}

it('AC-008: the grid joins the distinct customers and offers of a multi-offer row with " · "', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $row = collect(multiQuoteRows())->firstWhere('id', $workOrder->id);
    $sorted = collect($quotes)->sortBy('code')->values();

    expect($row['registry'])->toBe('Alfa Srl · Beta Spa')
        ->and($row['contract_number'])->toBe($sorted->pluck('code')->implode(' · '))
        ->and($row['quote'])->toBe($sorted->pluck('title')->unique()->implode(' · '))
        ->and($row['contract_expiry_date'])->toBeNull();
});

it('a mono-offer row keeps the registry {id, name} summary', function () {
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $row = collect(multiQuoteRows())->firstWhere('id', $workOrder->id);

    expect($row['registry'])->toBe(['id' => $workOrder->quote->opportunity->registry_id, 'name' => $workOrder->quote->opportunity->registry->name])
        ->and($row['contract_number'])->toBe($workOrder->quote->code);
});

it('AC-008: the registry set filter finds the multi-offer row through its lines', function () {
    [$workOrder] = multiQuoteReadFixture();
    $unrelated = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $ids = collect(multiQuoteRows(['filterModel' => ['registry' => ['filterType' => 'set', 'values' => ['Beta Spa']]]]))->pluck('id');

    expect($ids->all())->toBe([$workOrder->id])->and($ids)->not->toContain($unrelated->id);
});

it('the contract_number filter and quick search find the multi-offer row through its lines', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $code = $quotes[1]->code;

    $filtered = multiQuoteRows(['filterModel' => ['contract_number' => ['filterType' => 'text', 'type' => 'equals', 'filter' => $code]]]);
    $searched = multiQuoteRows(['search' => $code]);

    expect(collect($filtered)->pluck('id')->all())->toBe([$workOrder->id])
        ->and(collect($searched)->pluck('id')->all())->toBe([$workOrder->id]);
});

it('sorting by registry / offer keeps multi-offer rows last (null sort value)', function () {
    [$workOrder] = multiQuoteReadFixture();
    $mono = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    foreach (['registry', 'contract_number', 'quote', 'contract_expiry_date'] as $column) {
        expect(multiQuoteRows(['sortModel' => [['colId' => $column, 'sort' => 'desc']]]))->toHaveCount(2);
    }

    $ids = collect(multiQuoteRows(['sortModel' => [['colId' => 'registry', 'sort' => 'desc']]]))->pluck('id')->all();
    expect($ids)->toBe([$mono->id, $workOrder->id]);
});

it('distinct values of registry include the customers reached only through lines', function () {
    multiQuoteReadFixture();
    WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'registry', 'limit' => 25])
        ->assertOk()->json('data.values');

    expect($values)->toContain('Alfa Srl', 'Beta Spa')->toHaveCount(3);
});
