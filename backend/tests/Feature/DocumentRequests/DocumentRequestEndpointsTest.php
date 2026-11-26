<?php

use App\Enums\DocumentRequestOperation;
use App\Jobs\SyncDocumentRequestJob;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
| Document requests API (spec 0428): AC-001 (creation), AC-004, AC-005, AC-006,
| AC-007 (edit), AC-008, AC-009, AC-010 (queueing side). The job is covered in
| SyncDocumentRequestJobTest. No real call: the queue is faked.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

function documentRequestPayload(?int $checklistId, array $overrides = []): array
{
    return array_merge([
        'document_checklist_id' => $checklistId,
        'due_date' => now()->addDays(10)->toDateString(),
        'recipient_email' => 'destinatario@example.test',
        'message' => 'Carichi i documenti, grazie.',
        'send' => true,
    ], $overrides);
}

it('AC-001: creates the request pending and queues the upsert after the commit', function () {
    $workOrder = documentRequestWorkOrder();
    $category = documentRequestCategory('Onboarding');
    $checklist = documentRequestChecklist(['code' => 'APERTURA', 'name' => 'Apertura', 'category_id' => $category->id]);
    $actor = documentRequestUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/work-orders/{$workOrder->id}/document-requests", documentRequestPayload($checklist->id))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.delivery_status', 'pending')
        ->assertJsonPath('data.pending_operation', 'upsert')
        ->assertJsonPath('data.status', null)
        ->assertJsonPath('data.checklist', ['id' => $checklist->id, 'code' => 'APERTURA', 'name' => 'Apertura'])
        ->assertJsonPath('data.category_name', 'Onboarding')
        ->assertJsonPath('data.work_order_id', $workOrder->id);

    $request = DocumentRequest::query()->sole();
    expect($request->created_by)->toBe($actor->id)
        ->and($request->checklist_code)->toBe('APERTURA')
        ->and($request->recipient_email)->toBe('destinatario@example.test')
        ->and($response->json('data.id'))->toBe($request->id);
    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->documentRequestId === $request->id
        && $job->operation === DocumentRequestOperation::Upsert
        && $job->send === true
        && $job->afterCommit === true);
});

it('AC-001: send defaults to true and false is carried to the job', function (array $extra, bool $expected) {
    $workOrder = documentRequestWorkOrder();
    $checklist = documentRequestChecklist();
    Sanctum::actingAs(documentRequestUserWith(['create']));
    $payload = documentRequestPayload($checklist->id);
    unset($payload['send']);

    $this->postJson("/api/work-orders/{$workOrder->id}/document-requests", [...$payload, ...$extra])->assertCreated();

    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->send === $expected);
})->with([
    'omitted' => [[], true],
    'false' => [['send' => false], false],
]);

it('AC-004: rejects an inactive or not synced checklist, a past date, a bad email and a long message', function (string $field, callable $invalid) {
    $workOrder = documentRequestWorkOrder();
    Sanctum::actingAs(documentRequestUserWith(['create']));

    [$checklistId, $overrides] = $invalid();

    $this->postJson("/api/work-orders/{$workOrder->id}/document-requests", documentRequestPayload($checklistId, $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(DocumentRequest::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'inactive checklist' => ['document_checklist_id', fn () => [documentRequestChecklist(['is_active' => false])->id, []]],
    'pending checklist' => ['document_checklist_id', fn () => [documentRequestChecklist(['sync_status' => 'pending'])->id, []]],
    'failed checklist' => ['document_checklist_id', fn () => [documentRequestChecklist(['sync_status' => 'failed'])->id, []]],
    'missing checklist' => ['document_checklist_id', fn () => [999999, []]],
    'past due date' => ['due_date', fn () => [documentRequestChecklist()->id, ['due_date' => now()->subDay()->toDateString()]]],
    'invalid email' => ['recipient_email', fn () => [documentRequestChecklist()->id, ['recipient_email' => 'non-una-email']]],
    'missing email (AC-021)' => ['recipient_email', fn () => [documentRequestChecklist()->id, ['recipient_email' => null]]],
    'message of 2001 characters' => ['message', fn () => [documentRequestChecklist()->id, ['message' => str_repeat('a', 2001)]]],
]);

it('AC-004: accepts a message of 2000 characters, today as due date and only the required fields', function () {
    $workOrder = documentRequestWorkOrder();
    $checklist = documentRequestChecklist();
    Sanctum::actingAs(documentRequestUserWith(['create']));

    $this->postJson("/api/work-orders/{$workOrder->id}/document-requests", documentRequestPayload($checklist->id, ['message' => str_repeat('a', 2000), 'due_date' => now()->toDateString()]))->assertCreated();
    $this->postJson("/api/work-orders/{$workOrder->id}/document-requests", ['document_checklist_id' => $checklist->id, 'recipient_email' => 'destinatario@example.test'])->assertCreated();
});

it('AC-005: every endpoint answers 403 without the document-requests permission', function (string $method, string $uri) {
    $workOrder = documentRequestWorkOrder();
    $checklist = documentRequestChecklist();
    $request = documentRequestFor($workOrder, ['status' => 'sent']);
    Sanctum::actingAs(documentRequestUserWith([]));

    $this->json($method, str_replace(['{wo}', '{id}'], [(string) $workOrder->id, (string) $request->id], $uri), documentRequestPayload($checklist->id))
        ->assertForbidden();
    Queue::assertNothingPushed();
})->with([
    'index' => ['GET', '/api/work-orders/{wo}/document-requests'],
    'defaults' => ['GET', '/api/work-orders/{wo}/document-requests/defaults'],
    'store' => ['POST', '/api/work-orders/{wo}/document-requests'],
    'show' => ['GET', '/api/document-requests/{id}'],
    'update' => ['PUT', '/api/document-requests/{id}'],
    'remind' => ['POST', '/api/document-requests/{id}/remind'],
    'cancel' => ['POST', '/api/document-requests/{id}/cancel'],
    'refresh' => ['POST', '/api/document-requests/{id}/refresh'],
    'sync-logs' => ['GET', '/api/document-requests/{id}/sync-logs'],
    'for-select' => ['GET', '/api/document-checklists/for-select'],
]);

it('AC-005: every endpoint answers 403 without view on the work order', function (string $method, string $uri) {
    $workOrder = documentRequestWorkOrder();
    $checklist = documentRequestChecklist();
    $request = documentRequestFor($workOrder, ['status' => 'sent']);
    Sanctum::actingAs(documentRequestUserWith(['viewAny', 'view', 'create', 'update'], canViewWorkOrders: false));

    $this->json($method, str_replace(['{wo}', '{id}'], [(string) $workOrder->id, (string) $request->id], $uri), documentRequestPayload($checklist->id))
        ->assertForbidden();
    Queue::assertNothingPushed();
    expect(DocumentRequest::query()->count())->toBe(1);
})->with([
    'index' => ['GET', '/api/work-orders/{wo}/document-requests'],
    'defaults' => ['GET', '/api/work-orders/{wo}/document-requests/defaults'],
    'store' => ['POST', '/api/work-orders/{wo}/document-requests'],
    'show' => ['GET', '/api/document-requests/{id}'],
    'update' => ['PUT', '/api/document-requests/{id}'],
    'remind' => ['POST', '/api/document-requests/{id}/remind'],
    'cancel' => ['POST', '/api/document-requests/{id}/cancel'],
    'refresh' => ['POST', '/api/document-requests/{id}/refresh'],
    'sync-logs' => ['GET', '/api/document-requests/{id}/sync-logs'],
]);

it('AC-005: each ability opens only its own endpoints', function () {
    $workOrder = documentRequestWorkOrder();
    $request = documentRequestFor($workOrder, ['status' => 'sent']);

    Sanctum::actingAs(documentRequestUserWith(['viewAny']));
    $this->getJson("/api/work-orders/{$workOrder->id}/document-requests")->assertOk()->assertJsonPath('meta.can_create', false);
    $this->postJson("/api/document-requests/{$request->id}/remind")->assertForbidden();

    Sanctum::actingAs(documentRequestUserWith(['view']));
    $this->getJson("/api/document-requests/{$request->id}")->assertOk();
    $this->getJson("/api/document-requests/{$request->id}/sync-logs")->assertOk();
    $this->getJson("/api/work-orders/{$workOrder->id}/document-requests")->assertForbidden();
    $this->putJson("/api/document-requests/{$request->id}", [])->assertForbidden();

    Sanctum::actingAs(documentRequestUserWith(['update']));
    $this->postJson("/api/document-requests/{$request->id}/refresh")->assertOk();
    $this->getJson("/api/document-requests/{$request->id}")->assertForbidden();
});

it('AC-005: the list of a work order holds only its own requests, newest first, with can_create', function () {
    $workOrder = documentRequestWorkOrder();
    $first = documentRequestFor($workOrder, ['created_at' => now()->subDays(2)]);
    $second = documentRequestFor($workOrder, ['created_at' => now()->subDay()]);
    documentRequestFor(documentRequestWorkOrder());
    Sanctum::actingAs(documentRequestUserWith(['viewAny', 'create', 'update']));

    $this->getJson("/api/work-orders/{$workOrder->id}/document-requests")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.can_create', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.1.id', $first->id)
        ->assertJsonPath('data.0.permissions', ['update' => true, 'remind' => true, 'cancel' => true, 'review' => false, 'complete' => true]);
});

it('exposes the snapshot as sections and items with a documents count, never the files', function () {
    $workOrder = documentRequestWorkOrder();
    $request = documentRequestFor($workOrder);
    Sanctum::actingAs(documentRequestUserWith(['view']));

    $response = $this->getJson("/api/document-requests/{$request->id}")->assertOk();

    $response->assertJsonPath('data.sections', [['name' => 'Identita', 'description' => null]])
        ->assertJsonPath('data.items.0.name', 'Documento A')
        ->assertJsonPath('data.items.0.documents_count', 0)
        ->assertJsonPath('data.items.1.documents_count', 1)
        ->assertJsonPath('data.items.1.latest_validation', null);
    expect(json_encode($response->json()))->not->toContain('a.pdf');
});

it('AC-005: the checklist for-select lists only active and synced checklists, filtered by category', function () {
    $category = documentRequestCategory('Onboarding');
    $other = documentRequestCategory('Altro');
    $ok = documentRequestChecklist(['name' => 'Alfa', 'code' => 'ALFA', 'category_id' => $category->id]);
    documentRequestChecklist(['name' => 'Beta', 'category_id' => $other->id]);
    documentRequestChecklist(['name' => 'Disattiva', 'is_active' => false, 'category_id' => $category->id]);
    documentRequestChecklist(['name' => 'Non sincronizzata', 'sync_status' => 'pending', 'category_id' => $category->id]);
    Sanctum::actingAs(documentRequestUserWith(['create']));

    $this->getJson('/api/document-checklists/for-select')
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('pagination.total', 2);

    $this->getJson('/api/document-checklists/for-select?category_id='.$category->id)
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0', ['id' => $ok->id, 'label' => 'Alfa', 'code' => 'ALFA', 'category_name' => 'Onboarding'])
        ->assertJsonPath('pagination.total', 1);

    $this->getJson('/api/document-checklists/for-select?search=bet&limit=1&offset=0')
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.label', 'Beta');
});

it('AC-006: defaults return the primary email and the name of the customer, null when missing', function () {
    $workOrder = documentRequestWorkOrder('cliente@example.test');
    $without = documentRequestWorkOrder(null);
    Sanctum::actingAs(documentRequestUserWith(['create']));

    $this->getJson("/api/work-orders/{$workOrder->id}/document-requests/defaults")
        ->assertOk()
        ->assertJsonPath('data', ['recipient_email' => 'cliente@example.test', 'customer_name' => 'Alfa Srl']);

    $this->getJson("/api/work-orders/{$without->id}/document-requests/defaults")
        ->assertOk()
        ->assertJsonPath('data.recipient_email', null);
});

it('AC-006: a multi-offer work order uses the email of its first offer', function () {
    $workOrder = documentRequestMultiQuoteWorkOrder(['prima@example.test', 'seconda@example.test']);
    Sanctum::actingAs(documentRequestUserWith(['create']));

    $this->getJson("/api/work-orders/{$workOrder->id}/document-requests/defaults")
        ->assertOk()
        ->assertJsonPath('data.recipient_email', 'prima@example.test')
        ->assertJsonPath('data.customer_name', 'Cliente 0');
});

it('AC-007: edit queues an upsert with the new values and keeps checklist and work order', function () {
    $workOrder = documentRequestWorkOrder();
    $request = documentRequestFor($workOrder, ['checklist_code' => 'FISSO']);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->putJson("/api/document-requests/{$request->id}", [
        'due_date' => now()->addDays(5)->toDateString(),
        'recipient_email' => 'nuovo@example.test',
        'message' => 'Nuovo testo',
        'send' => false,
        'checklist_code' => 'ALTRO',
        'work_order_id' => 999,
    ])
        ->assertOk()
        ->assertJsonPath('data.pending_operation', 'upsert')
        ->assertJsonPath('data.delivery_status', 'pending')
        ->assertJsonPath('data.recipient_email', 'nuovo@example.test');

    $request->refresh();
    expect($request->checklist_code)->toBe('FISSO')
        ->and($request->work_order_id)->toBe($workOrder->id)
        ->and($request->message)->toBe('Nuovo testo');
    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::Upsert && $job->send === false);
});

it('AC-007: edit answers 422 on status without a job when the request is completed, expired or cancelled', function (string $status) {
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => $status]);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->putJson("/api/document-requests/{$request->id}", ['recipient_email' => 'a@example.test', 'message' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    Queue::assertNothingPushed();
    expect($request->fresh()->message)->toBeNull();
})->with(['completed', 'expired', 'cancelled']);

it('AC-007: edit validates the fields, but a past due date left unchanged is accepted', function () {
    $request = documentRequestFor(documentRequestWorkOrder(), ['due_date' => now()->subDays(3)->toDateString()]);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->putJson("/api/document-requests/{$request->id}", ['due_date' => now()->subDay()->toDateString(), 'recipient_email' => 'a@example.test'])
        ->assertUnprocessable()->assertJsonValidationErrors(['due_date']);
    $this->putJson("/api/document-requests/{$request->id}", ['recipient_email' => 'no'])
        ->assertUnprocessable()->assertJsonValidationErrors(['recipient_email']);
    $this->putJson("/api/document-requests/{$request->id}", ['message' => 'senza email'])
        ->assertUnprocessable()->assertJsonValidationErrors(['recipient_email']);

    $this->putJson("/api/document-requests/{$request->id}", ['due_date' => now()->subDays(3)->toDateString(), 'recipient_email' => 'a@example.test', 'message' => 'ok'])->assertOk();
});

it('AC-008: remind queues the operation on a sent or in progress request', function (string $status) {
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => $status]);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->postJson("/api/document-requests/{$request->id}/remind")
        ->assertOk()
        ->assertJsonPath('data.pending_operation', 'remind')
        ->assertJsonPath('data.delivery_status', 'pending');

    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::Remind);
})->with(['sent', 'in_progress']);

it('AC-008: remind answers 422 on status without a job on draft, completed, cancelled, expired or never answered', function (?string $status) {
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => $status]);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->postJson("/api/document-requests/{$request->id}/remind")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    Queue::assertNothingPushed();
})->with(['draft', 'completed', 'cancelled', 'expired', null]);

it('AC-009: cancel queues the operation on a sent request', function () {
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => 'sent']);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->postJson("/api/document-requests/{$request->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.pending_operation', 'cancel');

    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::Cancel);
});

it('AC-009: cancel on an already cancelled request answers 200 with nothing queued', function () {
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => 'cancelled']);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->postJson("/api/document-requests/{$request->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.delivery_status', 'delivered');

    Queue::assertNothingPushed();
});

it('AC-009: cancel answers 422 on a completed or expired request', function (string $status) {
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => $status]);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->postJson("/api/document-requests/{$request->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    Queue::assertNothingPushed();
})->with(['completed', 'expired']);

it('AC-022: cancel on a request that never reached ArchivioSmart cancels it only in QNet, with nothing queued', function () {
    $request = documentRequestFor(documentRequestWorkOrder(), [
        'status' => null,
        'delivery_status' => 'failed',
        'pending_operation' => 'refresh',
        'delivery_error' => 'Risposta imprevista da ArchivioSmart (404)',
    ]);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->postJson("/api/document-requests/{$request->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.delivery_status', 'delivered')
        ->assertJsonPath('data.pending_operation', null)
        ->assertJsonPath('data.delivery_error', null);

    expect($request->fresh()->cancelled_at)->not->toBeNull();
    Queue::assertNothingPushed();
});

it('AC-022: cancel while the first upsert is still queued goes through ArchivioSmart', function () {
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => null, 'delivery_status' => 'pending', 'pending_operation' => 'upsert']);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->postJson("/api/document-requests/{$request->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', null)
        ->assertJsonPath('data.pending_operation', 'cancel');

    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::Cancel);
});

it('AC-022: refresh answers 422 on status without a job on a request that never reached ArchivioSmart', function () {
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => null, 'delivery_status' => 'failed']);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->postJson("/api/document-requests/{$request->id}/refresh")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    Queue::assertNothingPushed();
});

it('AC-010: refresh queues the operation whatever the state', function () {
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => 'completed']);
    Sanctum::actingAs(documentRequestUserWith(['update']));

    $this->postJson("/api/document-requests/{$request->id}/refresh")
        ->assertOk()
        ->assertJsonPath('data.pending_operation', 'refresh');

    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::Refresh);
});

it('lists the last sync logs of a request', function () {
    $request = documentRequestFor(documentRequestWorkOrder());
    $request->syncLogs()->create(['operation' => 'upsert', 'outcome' => 'failed', 'http_status' => 422, 'attempt' => 1, 'message' => 'Errore']);
    Sanctum::actingAs(documentRequestUserWith(['view']));

    $this->getJson("/api/document-requests/{$request->id}/sync-logs")
        ->assertOk()
        ->assertJsonPath('data.0.operation', 'upsert')
        ->assertJsonPath('data.0.outcome', 'failed')
        ->assertJsonPath('data.0.http_status', 422)
        ->assertJsonPath('data.0.message', 'Errore');
});

it('answers 401 to unauthenticated callers', function () {
    $this->getJson('/api/document-requests/1')->assertUnauthorized();
    $this->getJson('/api/document-checklists/for-select')->assertUnauthorized();
});
