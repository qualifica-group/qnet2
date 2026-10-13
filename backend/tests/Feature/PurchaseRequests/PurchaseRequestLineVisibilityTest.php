<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\CostType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequestLine;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Status change, mass supplier and order generation on one line, as closures.
 *
 * @return array<int, Closure>
 */
function lineActions(object $test, int $lineId): array
{
    return [
        fn () => changeStatus($test, [$lineId], 'on_hold', ['reason' => 'Waiting']),
        fn () => $test->postJson('/api/purchase-request-lines/supplier', ['line_ids' => [$lineId], 'supplier_id' => Registry::factory()->create(['is_supplier' => true])->id]),
        fn () => $test->postJson('/api/purchase-request-lines/purchase-orders', ['line_ids' => [$lineId], 'cost_type_id' => CostType::factory()->create()->id]),
    ];
}

function withOrderCreation(User $actor): User
{
    purchaseOrderUserWith([]);
    $actor->givePermissionTo('purchase-orders.create');

    return $actor;
}

function approvedSuppliedRequest(array $attributes = []): PurchaseRequestLine
{
    $supplier = Registry::factory()->create(['is_supplier' => true]);

    return purchaseRequestWithLines([LineStatus::Approved], $attributes, ['supplier_id' => $supplier->id])->lines->first();
}

it('AC-007: fulfill or manage without viewAll on a foreign request gives 403 on every action', function (string $ability) {
    $actor = withOrderCreation(purchaseRequestUserWith(['view', $ability]));
    $line = approvedSuppliedRequest();
    Sanctum::actingAs($actor);

    foreach (lineActions($this, $line->id) as $action) {
        $action()->assertForbidden();
    }

    expect($line->fresh()->status)->toBe(LineStatus::Approved)
        ->and(PurchaseOrder::query()->count())->toBe(0);
})->with(['fulfill', 'manageStatuses']);

it('AC-008: fulfill without the view permission gives 403 even on a request one is part of', function () {
    $actor = withOrderCreation(purchaseRequestUserWith(['fulfill']));
    $line = approvedSuppliedRequest(['requester_id' => $actor->id]);
    Sanctum::actingAs($actor);

    foreach (lineActions($this, $line->id) as $action) {
        $action()->assertForbidden();
    }

    expect($line->fresh()->status)->toBe(LineStatus::Approved);
});
