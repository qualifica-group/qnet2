<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

it('AC-009: a forced closure needs a reason; with one the request closes and records who and why', function () {
    $actor = purchaseRequestUserWith(['view', 'viewAll', 'close']);
    Sanctum::actingAs($actor);
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Received]);

    $this->postJson("/api/purchase-requests/{$request->id}/close")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
    expect($request->fresh()->status->value)->toBe('open');

    $this->postJson("/api/purchase-requests/{$request->id}/close", ['reason' => 'Budget frozen'])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closed_by.id', $actor->id)
        ->assertJsonPath('data.close_reason', 'Budget frozen')
        ->assertJsonPath('data.abilities.close', false)
        ->assertJsonPath('data.abilities.update', false);

    expect($request->fresh()->closed_at)->not->toBeNull();
});

it('AC-009: closing needs the close permission', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $request = purchaseRequestWithLines([LineStatus::Approved]);

    $this->postJson("/api/purchase-requests/{$request->id}/close", ['reason' => 'x'])->assertForbidden();
    expect($request->fresh()->status->value)->toBe('open');
});

it('AC-009: a closed request answers 409 to PUT, status change and close', function () {
    $actor = purchaseRequestUserWith(['view', 'viewAll', 'update', 'close', 'manageStatuses']);
    Sanctum::actingAs($actor);
    $request = purchaseRequestWithLines([LineStatus::Received], ['status' => 'closed']);
    $changed = purchaseRequestUpdatePayload($request, ['subject' => 'Changed after closing']);

    $this->putJson("/api/purchase-requests/{$request->id}", $changed)->assertStatus(409)->assertJsonPath('success', false);
    $this->postJson('/api/purchase-request-lines/status', ['line_ids' => [$request->lines->first()->id], 'to_status' => 'approved'])->assertStatus(409);
    $this->postJson("/api/purchase-requests/{$request->id}/close", ['reason' => 'again'])->assertStatus(409);

    expect($request->fresh()->subject)->not->toBe('Changed after closing');
});

it('exposes the closure information: forced when lines are still open', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'close']));
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::PendingApproval, LineStatus::Received]);

    $this->getJson("/api/purchase-requests/{$request->id}/closure")
        ->assertOk()
        ->assertJsonPath('data.can_close', true)
        ->assertJsonPath('data.is_forced', true)
        ->assertJsonPath('data.non_terminal_count', 2)
        ->assertJsonPath('data.line_status_counts.approved', 1)
        ->assertJsonPath('data.line_status_counts.received', 1);
});

it('closure information reports can_close false without the close permission or once closed', function () {
    $request = purchaseRequestWithLines([LineStatus::Received]);

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $this->getJson("/api/purchase-requests/{$request->id}/closure")
        ->assertOk()
        ->assertJsonPath('data.can_close', false)
        ->assertJsonPath('data.is_forced', false)
        ->assertJsonPath('data.non_terminal_count', 0);

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'close']));
    $closed = PurchaseRequest::factory()->closed()->create();
    $this->getJson("/api/purchase-requests/{$closed->id}/closure")->assertOk()->assertJsonPath('data.can_close', false);
});

it('closes without a reason when nothing is left open', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'close']));
    $request = purchaseRequestWithLines([LineStatus::Received, LineStatus::Rejected]);

    $this->postJson("/api/purchase-requests/{$request->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.close_reason', null);
});
