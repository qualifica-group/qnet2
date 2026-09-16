<?php

use App\Models\Contract;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
 * The Commessa detail names its Contratto, not the underlying offer (user
 * directive 2026-09-16): WorkOrderResource exposes `contract` as the
 * `{ id, code, title }` of the Contract born from the linked quote, `null`
 * while that quote has no contract.
 */

function contractSummaryActor(): User
{
    foreach (['view', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['work-orders.view', 'work-orders.viewAll']);

    return $user;
}

it('exposes the contract of the linked quote as { id, code, title }', function () {
    $quote = Quote::factory()->create();
    $contract = Contract::factory()->create(['quote_id' => $quote->id]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs(contractSummaryActor());

    $response = $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk();

    expect($response->json('data.contract'))->toBe([
        'id' => $contract->id,
        'code' => $quote->code,
        'title' => $quote->title,
    ]);
});

it('exposes a null contract while the linked quote has none', function () {
    $quote = Quote::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs(contractSummaryActor());

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.contract', null);
});
