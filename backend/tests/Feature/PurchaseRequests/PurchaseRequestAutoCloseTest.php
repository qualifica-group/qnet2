<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Enums\PurchaseRequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-009: removing the last open line by PUT closes the request', function () {
    $actor = purchaseRequestUserWith(['view', 'viewAll', 'update', 'deleteLine']);
    Sanctum::actingAs($actor);
    $request = purchaseRequestWithLines([LineStatus::Received, LineStatus::PendingApproval]);

    $payload = purchaseRequestUpdatePayload($request);
    $payload['lines'] = [$payload['lines'][0]];

    $this->putJson("/api/purchase-requests/{$request->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonCount(1, 'data.lines');

    $request->refresh();
    expect($request->status)->toBe(PurchaseRequestStatus::Closed)
        ->and($request->closed_by)->toBe($actor->id)
        ->and($request->closed_at)->not->toBeNull();
});

it('AC-009: a PUT that leaves an open line keeps the request open', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines([LineStatus::Received, LineStatus::PendingApproval]);

    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request))
        ->assertOk()
        ->assertJsonPath('data.status', 'open');

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::Open);
});
