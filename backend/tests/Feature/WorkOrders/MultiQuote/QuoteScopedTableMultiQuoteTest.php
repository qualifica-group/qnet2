<?php

use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function scopedMultiQuoteIds(array $scope): array
{
    return collect(test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25, ...$scope])
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

it('AC-008: the contract tab of each source offer lists the multi-offer commessa', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    $other = Quote::factory()->create();
    $mono = WorkOrder::factory()->create(['quote_id' => $quotes[0]->id]);
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    expect(scopedMultiQuoteIds(['quoteId' => $quotes[0]->id]))->toBe(collect([$workOrder->id, $mono->id])->sort()->values()->all())
        ->and(scopedMultiQuoteIds(['quoteId' => $quotes[1]->id]))->toBe([$workOrder->id])
        ->and(scopedMultiQuoteIds(['quoteId' => $other->id]))->toBe([]);
});

it('D-9: the registry tab lists the multi-offer commesse holding a line of that customer', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    $unrelated = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $registryA = $quotes[0]->opportunity->registry_id;
    $registryB = $quotes[1]->opportunity->registry_id;

    expect(scopedMultiQuoteIds(['registryId' => $registryA]))->toBe([$workOrder->id])
        ->and(scopedMultiQuoteIds(['registryId' => $registryB]))->toBe([$workOrder->id])
        ->and(scopedMultiQuoteIds(['registryId' => $unrelated->quote->opportunity->registry_id]))->toBe([$unrelated->id]);
});
