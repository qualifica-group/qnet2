<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

// ---------------------------------------------------------------------------
// AC-001 / AC-002 — create
// ---------------------------------------------------------------------------

it('AC-001: creates a request with two pending lines and computes every amount server-side', function () {
    $actor = purchaseRequestUserWith(['view', 'create']);
    Sanctum::actingAs($actor);
    $payload = purchaseRequestPayload(['grand_total' => '999.99', 'created_by' => 12345], 2);
    $payload['lines'][0]['taxable_amount'] = '999.00';
    $payload['lines'][0]['total_amount'] = '999.00';

    $response = $this->postJson('/api/purchase-requests', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.created_by.id', $actor->id)
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.taxable_total', '40.04')
        ->assertJsonPath('data.vat_total', '8.80')
        ->assertJsonPath('data.grand_total', '48.84')
        ->assertJsonPath('data.lines.0.status', 'pending_approval')
        ->assertJsonPath('data.lines.0.quantity', '2.000')
        ->assertJsonPath('data.lines.0.unit_price', '10.01')
        ->assertJsonPath('data.lines.0.taxable_amount', '20.02')
        ->assertJsonPath('data.lines.0.vat_amount', '4.40')
        ->assertJsonPath('data.lines.0.total_amount', '24.42')
        ->assertJsonPath('data.lines.1.position', 2)
        ->assertJsonPath('data.line_status_counts.pending_approval', 2)
        ->assertJsonPath('data.line_status_counts.approved', 0)
        ->assertJsonPath('data.abilities.update', false)
        ->assertJsonStructure(['permissions', 'data' => [
            'id', 'subject', 'requested_at', 'priority', 'requester', 'function_manager', 'customer', 'supplier',
            'work_order', 'company', 'company_site', 'operational_site', 'business_function', 'created_by',
            'notes', 'delivery_terms', 'procurement_plan', 'technical_requirements', 'special_conditions',
            'closed_by', 'closed_at', 'close_reason', 'lines', 'created_at', 'updated_at',
            'abilities' => ['update', 'delete', 'close', 'notify_manager', 'view_activity'],
            'field_permissions' => ['subject' => ['visible', 'editable', 'required']],
        ]]);

    expect($response->json('data.requested_at'))->toBe('2026-10-09');
    expect(PurchaseRequest::query()->count())->toBe(1)
        ->and(PurchaseRequestLine::query()->count())->toBe(2)
        ->and(PurchaseRequest::query()->first()->created_by)->toBe($actor->id);
});

it('AC-001: forbids creating without the create permission', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view']));

    $this->postJson('/api/purchase-requests', purchaseRequestPayload())->assertForbidden();
    expect(PurchaseRequest::query()->count())->toBe(0);
});

it('AC-002: rejects a request without lines, with zero quantity, a foreign company site or a non supplier', function (string $key, callable $mutate) {
    Sanctum::actingAs(purchaseRequestUserWith(['create']));
    $payload = $mutate(purchaseRequestPayload());

    $this->postJson('/api/purchase-requests', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($key);

    expect(PurchaseRequest::query()->count())->toBe(0);
})->with([
    'no lines' => ['lines', fn (array $p) => [...$p, 'lines' => []]],
    'zero quantity' => ['lines.0.quantity', function (array $p) {
        $p['lines'][0]['quantity'] = 0;

        return $p;
    }],
    'negative price' => ['lines.0.unit_price', function (array $p) {
        $p['lines'][0]['unit_price'] = -1;

        return $p;
    }],
    'site of another company' => ['company_site_id', fn (array $p) => [...$p, 'company_site_id' => purchaseRequestPayload()['company_site_id']]],
    'supplier without the flag' => ['supplier_id', fn (array $p) => [...$p, 'supplier_id' => supplierRegistry(false)->id]],
    'inactive manager' => ['function_manager_id', fn (array $p) => [...$p, 'function_manager_id' => User::factory()->create(['is_active' => false])->id]],
    'footer too long' => ['notes', fn (array $p) => [...$p, 'notes' => str_repeat('a', 5001)]],
]);

it('accepts an active supplier and stores the footer fields', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['create']));
    $supplier = supplierRegistry();

    $this->postJson('/api/purchase-requests', purchaseRequestPayload([
        'supplier_id' => $supplier->id,
        'delivery_terms' => 'Within 30 days',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.supplier.id', $supplier->id)
        ->assertJsonPath('data.delivery_terms', 'Within 30 days');
});

// ---------------------------------------------------------------------------
// AC-010 / AC-011 — update with line sync
// ---------------------------------------------------------------------------

it('AC-010: saves header and pending line changes but refuses to change a line that is no longer pending', function () {
    Sanctum::actingAs($actor = purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::Approved]);

    $changedApproved = purchaseRequestUpdatePayload($request);
    $changedApproved['lines'][1]['quantity'] = '5';

    $this->putJson("/api/purchase-requests/{$request->id}", $changedApproved)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines.1');
    expect($request->lines->last()->fresh()->quantity)->toBe('1.000');

    $payload = purchaseRequestUpdatePayload($request, ['subject' => 'Renamed']);
    $payload['lines'][0]['quantity'] = '3';
    $payload['lines'][0]['description'] = 'Edited';

    $this->putJson("/api/purchase-requests/{$request->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.subject', 'Renamed')
        ->assertJsonPath('data.lines.0.description', 'Edited')
        ->assertJsonPath('data.lines.0.taxable_amount', '300.00')
        ->assertJsonPath('data.lines.1.status', 'approved')
        ->assertJsonPath('data.taxable_total', '400.00')
        ->assertJsonPath('data.abilities.update', true);
});

it('AC-010: adds a new pending line and rejects a line id of another request', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines([LineStatus::Approved]);
    $foreign = purchaseRequestWithLines()->lines->first();

    $payload = purchaseRequestUpdatePayload($request);
    $payload['lines'][] = ['description' => 'New one', 'quantity' => '2', 'unit_price' => '50'];

    $this->putJson("/api/purchase-requests/{$request->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.lines.1.description', 'New one')
        ->assertJsonPath('data.lines.1.status', 'pending_approval')
        ->assertJsonPath('data.grand_total', '200.00');

    $payload['lines'][1]['id'] = $foreign->id;
    $this->putJson("/api/purchase-requests/{$request->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines.1.id');
});

it('AC-011: deleting a line by omission needs deleteLine and only pending or rejected lines can go', function () {
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::Rejected, LineStatus::Ordered]);
    $withoutPermission = purchaseRequestUserWith(['view', 'viewAll', 'update']);
    $withPermission = purchaseRequestUserWith(['view', 'viewAll', 'update', 'deleteLine']);
    $keepOnlyFirst = purchaseRequestUpdatePayload($request);
    $keepOnlyFirst['lines'] = [$keepOnlyFirst['lines'][0]];

    Sanctum::actingAs($withoutPermission);
    $this->putJson("/api/purchase-requests/{$request->id}", $keepOnlyFirst)->assertForbidden();

    Sanctum::actingAs($withPermission);
    $this->putJson("/api/purchase-requests/{$request->id}", $keepOnlyFirst)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines');
    expect($request->lines()->count())->toBe(3);

    $dropRejected = purchaseRequestUpdatePayload($request);
    $dropRejected['lines'] = [$dropRejected['lines'][0], $dropRejected['lines'][2]];

    $this->putJson("/api/purchase-requests/{$request->id}", $dropRejected)
        ->assertOk()
        ->assertJsonCount(2, 'data.lines');
    expect($request->lines()->count())->toBe(2);
});

it('forbids updating without the update permission or outside the visibility scope', function () {
    $request = purchaseRequestWithLines();

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request))->assertForbidden();

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'update']));
    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request))->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-012 — delete
// ---------------------------------------------------------------------------

it('AC-012: refuses to delete a request with a received line', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'delete']));
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::Received]);

    $this->deleteJson("/api/purchase-requests/{$request->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false);

    expect(PurchaseRequest::query()->count())->toBe(1);
});

it('AC-012: deletes the request, its lines and logs', function () {
    Sanctum::actingAs($actor = purchaseRequestUserWith(['view', 'viewAll', 'delete']));
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::Rejected]);
    $request->lines->first()->statusLogs()->create(['user_id' => $actor->id, 'from_status' => null, 'to_status' => LineStatus::PendingApproval]);

    $this->deleteJson("/api/purchase-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseCount('purchase_requests', 0);
    $this->assertDatabaseCount('purchase_request_lines', 0);
    $this->assertDatabaseCount('purchase_request_line_status_logs', 0);
});

it('AC-012: forbids deleting without the delete permission', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines();

    $this->deleteJson("/api/purchase-requests/{$request->id}")->assertForbidden();
    expect(PurchaseRequest::query()->count())->toBe(1);
});

it('answers 404 for an unknown request', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));

    $this->getJson('/api/purchase-requests/999999')->assertNotFound()->assertJsonPath('success', false);
});
