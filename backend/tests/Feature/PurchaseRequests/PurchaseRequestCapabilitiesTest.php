<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * The capabilities of the actor on the first line, from the detail and from the lines grid.
 *
 * @return array{0: array<int, string>, 1: array<int, string>}
 */
function lineCapabilities(object $test, int $requestId): array
{
    $detail = $test->getJson("/api/purchase-requests/{$requestId}")->assertOk()->json('data.lines.0.abilities.capabilities');
    $grid = $test->postJson('/api/tables/purchase-request-lines/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items.0.abilities.capabilities');

    return [$detail, $grid];
}

it('AC-021: the assigned function manager gets [approve]', function () {
    $manager = purchaseRequestUserWith(['view']);
    $request = purchaseRequestWithLines([LineStatus::PendingApproval], ['function_manager_id' => $manager->id]);
    Sanctum::actingAs($manager);

    expect(lineCapabilities($this, $request->id))->toBe([['approve'], ['approve']]);
});

it('AC-021: the fulfill permission gets [fulfill]', function () {
    $request = purchaseRequestWithLines([LineStatus::Approved]);
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'fulfill']));

    expect(lineCapabilities($this, $request->id))->toBe([['fulfill'], ['fulfill']]);
});

it('AC-021: the manageStatuses permission gets [manage]', function () {
    $request = purchaseRequestWithLines([LineStatus::Approved]);
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'manageStatuses']));

    expect(lineCapabilities($this, $request->id))->toBe([['manage'], ['manage']]);
});

it('AC-021: a closed request exposes no capability', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo(purchaseRequestUserWith(['view', 'viewAll', 'fulfill', 'manageStatuses'])->getAllPermissions());
    $request = purchaseRequestWithLines([LineStatus::Received], ['function_manager_id' => $manager->id, 'status' => 'closed']);
    Sanctum::actingAs($manager);

    expect(lineCapabilities($this, $request->id))->toBe([[], []]);
});
