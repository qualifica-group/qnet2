<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestLineStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function changeStatus(object $test, array $lineIds, string $to, array $extra = []): TestResponse
{
    return $test->postJson('/api/purchase-request-lines/status', ['line_ids' => $lineIds, 'to_status' => $to] + $extra);
}

// ---------------------------------------------------------------------------
// AC-004 — the assigned function manager approves
// ---------------------------------------------------------------------------

it('AC-004: the assigned function manager approves a pending line, with log and approval data', function () {
    $manager = User::factory()->create();
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::PendingApproval], ['function_manager_id' => $manager->id]);
    $line = $request->lines->first();
    Sanctum::actingAs($manager);

    changeStatus($this, [$line->id], 'approved', ['reason' => 'Looks fine'])
        ->assertOk()
        ->assertJsonPath('data.updated_count', 1)
        ->assertJsonPath('data.closed_purchase_request_ids', []);

    $line->refresh();
    expect($line->status)->toBe(LineStatus::Approved)
        ->and($line->approved_by)->toBe($manager->id)
        ->and($line->approved_at)->not->toBeNull();

    $log = PurchaseRequestLineStatusLog::query()->sole();
    expect($log->purchase_request_line_id)->toBe($line->id)
        ->and($log->from_status)->toBe(LineStatus::PendingApproval)
        ->and($log->to_status)->toBe(LineStatus::Approved)
        ->and($log->user_id)->toBe($manager->id)
        ->and($log->reason)->toBe('Looks fine')
        ->and($log->bulk_group_id)->toBeNull();
});

it('AC-004: a user who is not the function manager of that request gets 403 and nothing changes', function () {
    $request = purchaseRequestWithLines();
    $line = $request->lines->first();
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));

    changeStatus($this, [$line->id], 'approved')->assertForbidden();

    expect($line->fresh()->status)->toBe(LineStatus::PendingApproval);
    $this->assertDatabaseCount('purchase_request_line_status_logs', 0);
});

it('AC-004: the manager can reject but cannot order', function () {
    $manager = User::factory()->create();
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::PendingApproval], ['function_manager_id' => $manager->id]);
    Sanctum::actingAs($manager);

    changeStatus($this, [$request->lines[0]->id], 'rejected')->assertOk();
    changeStatus($this, [$request->lines[1]->id], 'ordered')->assertUnprocessable()->assertJsonValidationErrors('line_ids.0');

    expect($request->lines[0]->fresh()->status)->toBe(LineStatus::Rejected)
        ->and($request->lines[0]->fresh()->approved_by)->toBe($manager->id);
});

// ---------------------------------------------------------------------------
// AC-005 / AC-006 — fulfill and manageStatuses
// ---------------------------------------------------------------------------

it('AC-005: fulfill cannot skip approval, but orders an approved line and receives an ordered one', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::Approved]);
    [$pending, $approved] = [$request->lines[0], $request->lines[1]];

    changeStatus($this, [$pending->id], 'ordered')->assertUnprocessable()->assertJsonValidationErrors('line_ids.0');
    changeStatus($this, [$approved->id], 'ordered')->assertOk();
    expect($approved->fresh()->status)->toBe(LineStatus::Ordered);

    changeStatus($this, [$approved->id], 'received')->assertOk();
    expect($approved->fresh()->status)->toBe(LineStatus::Received);
});

it('AC-005: fulfill parks an approved line on hold but cannot approve', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::PendingApproval]);

    changeStatus($this, [$request->lines[0]->id], 'on_hold')->assertOk();
    changeStatus($this, [$request->lines[1]->id], 'approved')->assertUnprocessable();
});

it('AC-006: manageStatuses moves a line to any other status, but not to the current one', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['manageStatuses']));
    $request = purchaseRequestWithLines([LineStatus::Received, LineStatus::PendingApproval]);
    $received = $request->lines[0];

    changeStatus($this, [$received->id], 'pending_approval')->assertOk();
    expect($received->fresh()->status)->toBe(LineStatus::PendingApproval)
        ->and($received->fresh()->approved_by)->toBeNull();

    changeStatus($this, [$received->id], 'received')->assertOk();
    changeStatus($this, [$received->id], 'received')->assertUnprocessable()->assertJsonValidationErrors('line_ids.0');
});

it('exposes the allowed transitions of each line to the actor', function () {
    $manager = purchaseRequestUserWith(['view']);
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::Approved], ['function_manager_id' => $manager->id]);
    $viewer = purchaseRequestUserWith(['view', 'viewAll', 'fulfill']);

    Sanctum::actingAs($manager);
    $this->getJson("/api/purchase-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.lines.0.abilities.transitions', ['approved', 'rejected'])
        ->assertJsonPath('data.lines.1.abilities.transitions', []);

    Sanctum::actingAs($viewer);
    $this->getJson("/api/purchase-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.lines.0.abilities.transitions', [])
        ->assertJsonPath('data.lines.1.abilities.transitions', ['ordered', 'on_hold']);
});

// ---------------------------------------------------------------------------
// AC-007 — mass change, all or nothing
// ---------------------------------------------------------------------------

it('AC-007: a mass change with one line not allowed changes none of them', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Approved, LineStatus::PendingApproval]);

    changeStatus($this, $request->lines->pluck('id')->all(), 'ordered')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('line_ids.2');

    expect($request->lines()->where('status', LineStatus::Ordered->value)->count())->toBe(0);
    $this->assertDatabaseCount('purchase_request_line_status_logs', 0);
});

it('AC-007: a mass change across requests shares one bulk group and logs every line', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $first = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Approved]);
    $second = purchaseRequestWithLines([LineStatus::Approved]);
    $ids = [...$first->lines->pluck('id')->all(), $second->lines->first()->id];

    changeStatus($this, $ids, 'ordered')
        ->assertOk()
        ->assertJsonPath('data.updated_count', 3);

    $groups = PurchaseRequestLineStatusLog::query()->pluck('bulk_group_id')->unique();
    expect(PurchaseRequestLineStatusLog::query()->count())->toBe(3)
        ->and($groups)->toHaveCount(1)
        ->and($groups->first())->not->toBeNull();
});

it('AC-007: a mass change including a request the actor cannot touch is refused with 403 and changes nothing', function () {
    $manager = User::factory()->create();
    $mine = purchaseRequestWithLines([LineStatus::PendingApproval], ['function_manager_id' => $manager->id]);
    $other = purchaseRequestWithLines();
    Sanctum::actingAs($manager);

    changeStatus($this, [$mine->lines->first()->id, $other->lines->first()->id], 'approved')->assertForbidden();

    expect(PurchaseRequestLine::query()->where('status', LineStatus::Approved->value)->count())->toBe(0);
});

it('validates the payload of the status endpoint', function (array $payload, string $key) {
    Sanctum::actingAs(purchaseRequestUserWith(['manageStatuses']));

    $this->postJson('/api/purchase-request-lines/status', $payload)->assertUnprocessable()->assertJsonValidationErrors($key);
})->with([
    'no lines' => [['line_ids' => [], 'to_status' => 'approved'], 'line_ids'],
    'unknown line' => [['line_ids' => [999999], 'to_status' => 'approved'], 'line_ids.0'],
    'unknown status' => [['line_ids' => [1], 'to_status' => 'shipped'], 'to_status'],
]);

// ---------------------------------------------------------------------------
// AC-008 — automatic closure
// ---------------------------------------------------------------------------

it('AC-008: the request closes by itself when the last open line reaches a terminal status', function () {
    $actor = purchaseRequestUserWith(['manageStatuses']);
    $request = purchaseRequestWithLines([LineStatus::Received, LineStatus::Rejected, LineStatus::Approved, LineStatus::Ordered]);
    Sanctum::actingAs($actor);

    changeStatus($this, [$request->lines[2]->id], 'on_hold')
        ->assertOk()
        ->assertJsonPath('data.closed_purchase_request_ids', []);
    expect($request->fresh()->status->value)->toBe('open');

    changeStatus($this, [$request->lines[3]->id], 'received')
        ->assertOk()
        ->assertJsonPath('data.closed_purchase_request_ids', [$request->id]);

    $request->refresh();
    expect($request->status->value)->toBe('closed')
        ->and($request->closed_by)->toBe($actor->id)
        ->and($request->closed_at)->not->toBeNull()
        ->and($request->close_reason)->toBeNull();
});

it('AC-008: a rejection that leaves nothing to work on closes the request too', function () {
    $manager = User::factory()->create();
    $request = purchaseRequestWithLines([LineStatus::Received, LineStatus::PendingApproval], ['function_manager_id' => $manager->id]);
    Sanctum::actingAs($manager);

    changeStatus($this, [$request->lines[1]->id], 'rejected')
        ->assertOk()
        ->assertJsonPath('data.closed_purchase_request_ids', [$request->id]);
});

it('AC-009: a status change on a closed request is refused with 409', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['manageStatuses']));
    $request = purchaseRequestWithLines([LineStatus::Received], ['status' => 'closed']);

    changeStatus($this, [$request->lines->first()->id], 'approved')->assertStatus(409)->assertJsonPath('success', false);
    expect($request->lines->first()->fresh()->status)->toBe(LineStatus::Received);
});

// ---------------------------------------------------------------------------
// status history
// ---------------------------------------------------------------------------

it('lists the status history of a line, most recent first, to who can view the request', function () {
    $manager = User::factory()->create();
    $request = purchaseRequestWithLines([LineStatus::PendingApproval], ['function_manager_id' => $manager->id]);
    $line = $request->lines->first();
    Sanctum::actingAs($manager);
    changeStatus($this, [$line->id], 'approved', ['reason' => 'ok'])->assertOk();

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'fulfill']));
    changeStatus($this, [$line->id], 'ordered')->assertOk();

    $this->getJson("/api/purchase-request-lines/{$line->id}/status-logs")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.to_status', 'ordered')
        ->assertJsonPath('data.0.from_status', 'approved')
        ->assertJsonPath('data.0.is_bulk', false)
        ->assertJsonPath('data.1.to_status', 'approved')
        ->assertJsonPath('data.1.reason', 'ok')
        ->assertJsonStructure(['data' => [['id', 'user' => ['id', 'name'], 'from_status', 'to_status', 'reason', 'is_bulk', 'created_at']]]);

    Sanctum::actingAs(purchaseRequestUserWith(['view']));
    $this->getJson("/api/purchase-request-lines/{$line->id}/status-logs")->assertForbidden();
});
