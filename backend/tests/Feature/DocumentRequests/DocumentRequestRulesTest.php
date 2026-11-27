<?php

use App\Enums\DocumentRequestDeliveryStatus;
use App\Enums\DocumentRequestOperation;
use App\Jobs\SyncDocumentRequestJob;
use App\Models\DocumentChecklist;
use App\Models\DocumentRequest;
use App\Services\ArchivioSmart\DocumentChecklistPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
| Corrected checklist rules on open requests (spec 0432): AC-001..AC-007.
| Endpoints with a faked queue, then the job against a faked Http.
*/

uses(RefreshDatabase::class);

const RULES_REVIEWER = 'Mario Bianchi';

function rulesUrl(DocumentRequest $request, string $path): string
{
    return "/api/document-requests/{$request->id}/{$path}";
}

/**
 * A sent request whose item 11 holds a rejected file and item 12 a file still waiting for the AI.
 *
 * @param  array<string, mixed>  $attributes
 */
function rulesRequest(array $attributes = []): DocumentRequest
{
    $request = documentRequestWithFiles(documentRequestWorkOrder(), $attributes);
    $snapshot = $request->snapshot;
    $snapshot['items'][1]['status'] = 'validating';
    $snapshot['items'][1]['documents'] = [['id' => 102, 'status' => 'validating', 'original_name' => 'Visura.pdf']];
    $request->forceFill(['snapshot' => $snapshot])->save();

    return $request;
}

/**
 * @param  array<string, mixed>  $arguments
 */
function runRulesJob(DocumentRequest $request, DocumentRequestOperation $operation, array $arguments = []): SyncDocumentRequestJob
{
    $job = (new SyncDocumentRequestJob($request->id, $operation, true, $arguments))->withFakeQueueInteractions();
    $job->job->attempts = 1;
    app()->call([$job, 'handle']);

    return $job;
}

function rulesActivity(string $event): ?object
{
    return DB::table('activity_log')->where('event', $event)->first();
}

/**
 * A checklist with one request per state, one never answered and one request of another checklist.
 *
 * @return array{0: DocumentChecklist, 1: array<string, DocumentRequest>}
 */
function rulesChecklistWithRequests(string $syncStatus = 'synced'): array
{
    $checklist = documentRequestChecklist(['sync_status' => $syncStatus]);
    $workOrder = documentRequestWorkOrder();
    $requests = [];
    foreach (['draft', 'sent', 'in_progress', 'completed', 'cancelled'] as $status) {
        $requests[$status] = documentRequestFor($workOrder, ['document_checklist_id' => $checklist->id, 'status' => $status]);
    }
    $requests['never_answered'] = documentRequestFor($workOrder, ['document_checklist_id' => $checklist->id, 'status' => null, 'delivery_status' => 'failed']);
    documentRequestFor($workOrder, ['status' => 'sent']);

    return [$checklist, $requests];
}

function actAsChecklistEditor(bool $withRequestUpdate = true): void
{
    $user = documentRequestUserWith($withRequestUpdate ? ['update'] : [], canViewWorkOrders: false);
    foreach (['view', 'update'] as $ability) {
        $user->givePermissionTo(Permission::findOrCreate("document-checklists.{$ability}"));
    }
    Sanctum::actingAs($user);
}

describe('request endpoints', function () {
    beforeEach(function () {
        Queue::fake();
    });

    it('AC-001: refresh-rules queues the operation on an open request and records who asked', function (string $status) {
        $request = rulesRequest(['status' => $status]);
        $user = documentRequestUserWith(['view', 'update']);
        Sanctum::actingAs($user);

        $this->postJson(rulesUrl($request, 'refresh-rules'))
            ->assertOk()
            ->assertJsonPath('data.pending_operation', 'refresh_rules')
            ->assertJsonPath('data.delivery_status', 'pending');

        Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::RefreshRules
            && $job->documentRequestId === $request->id
            && $job->afterCommit === true);
        $activity = rulesActivity('rules_refresh_requested');
        expect($activity->subject_id)->toBe($request->id)->and($activity->causer_id)->toBe($user->id);
    })->with(['draft', 'sent', 'in_progress']);

    it('AC-001: refresh-rules answers 422 on status for a closed or never answered request', function (?string $status) {
        $request = rulesRequest(['status' => $status, 'delivery_status' => 'failed']);
        Sanctum::actingAs(documentRequestUserWith(['view', 'update']));

        $this->postJson(rulesUrl($request, 'refresh-rules'))->assertUnprocessable()->assertJsonValidationErrors('status');
        Queue::assertNothingPushed();
        expect(rulesActivity('rules_refresh_requested'))->toBeNull();
    })->with(['completed', 'expired', 'cancelled', null]);

    it('AC-001: refresh-rules needs update and view on the work order', function () {
        $request = rulesRequest();

        Sanctum::actingAs(documentRequestUserWith(['view', 'review']));
        $this->postJson(rulesUrl($request, 'refresh-rules'))->assertForbidden();
        Sanctum::actingAs(documentRequestUserWith(['update'], canViewWorkOrders: false));
        $this->postJson(rulesUrl($request, 'refresh-rules'))->assertForbidden();

        Queue::assertNothingPushed();
    });

    it('AC-001: the resource exposes refresh_rules and the changed items', function (string $status, bool $allowed) {
        $request = rulesRequest(['status' => $status, 'rules_updated_item_ids' => [11]]);
        Sanctum::actingAs(documentRequestUserWith(['view', 'update']));

        $this->getJson("/api/document-requests/{$request->id}")->assertOk()
            ->assertJsonPath('data.permissions.refresh_rules', $allowed)
            ->assertJsonPath('data.rules_updated_item_ids', [11]);
    })->with([['draft', true], ['in_progress', true], ['completed', false]]);

    it('AC-003: revalidate queues one call per item, drops them from the changed items and records who asked', function () {
        $request = rulesRequest(['status' => 'in_progress', 'rules_updated_item_ids' => [11, 12, 13]]);
        $user = documentRequestUserWith(['view', 'review']);
        Sanctum::actingAs($user);

        $this->postJson(rulesUrl($request, 'revalidate'), ['item_ids' => [11, 12]])
            ->assertOk()
            ->assertJsonPath('data.pending_operation', 'revalidate')
            ->assertJsonPath('data.rules_updated_item_ids', [13]);

        Queue::assertPushed(SyncDocumentRequestJob::class, 2);
        foreach ([11, 12] as $itemId) {
            Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::Revalidate
                && $job->arguments === ['item_id' => $itemId]
                && $job->afterCommit === true);
        }
        $activity = rulesActivity('revalidation_requested');
        expect($activity->causer_id)->toBe($user->id)
            ->and(json_decode($activity->properties, true))->toBe(['item_ids' => [11, 12]]);
    });

    it('AC-003: revalidate refuses an unknown item, an item without files, a closed request and a bad body', function (array $attributes, array $body, string $field) {
        $request = rulesRequest(['status' => 'sent', ...$attributes]);
        if ($field === 'document') {
            $snapshot = $request->snapshot;
            $snapshot['items'][1]['documents'] = [];
            $request->forceFill(['snapshot' => $snapshot])->save();
        }
        Sanctum::actingAs(documentRequestUserWith(['view', 'review']));

        $this->postJson(rulesUrl($request, 'revalidate'), $body)->assertUnprocessable()->assertJsonValidationErrors($field);
        Queue::assertNothingPushed();
        expect(rulesActivity('revalidation_requested'))->toBeNull();
    })->with([
        'unknown item' => [[], ['item_ids' => [11, 999]], 'item'],
        'item without files' => [[], ['item_ids' => [11, 12]], 'document'],
        'completed request' => [['status' => 'completed'], ['item_ids' => [11]], 'status'],
        'draft request' => [['status' => 'draft'], ['item_ids' => [11]], 'status'],
        'missing ids' => [[], [], 'item_ids'],
        'empty ids' => [[], ['item_ids' => []], 'item_ids'],
        'duplicated ids' => [[], ['item_ids' => [11, 11]], 'item_ids.0'],
    ]);

    it('AC-003 and AC-005: revalidate and stop-validation need review, not update', function () {
        $request = rulesRequest();

        Sanctum::actingAs(documentRequestUserWith(['view', 'update']));
        $this->postJson(rulesUrl($request, 'revalidate'), ['item_ids' => [11]])->assertForbidden();
        $this->postJson(rulesUrl($request, 'revalidate'), [])->assertForbidden();
        $this->postJson(rulesUrl($request, 'items/12/stop-validation'))->assertForbidden();
        Sanctum::actingAs(documentRequestUserWith(['review'], canViewWorkOrders: false));
        $this->postJson(rulesUrl($request, 'items/12/stop-validation'))->assertForbidden();

        Queue::assertNothingPushed();
    });

    it('AC-005: stop-validation queues the call with the name of the user', function (string $itemStatus) {
        $request = rulesRequest(['status' => 'in_progress']);
        $snapshot = $request->snapshot;
        $snapshot['items'][1]['status'] = $itemStatus;
        $request->forceFill(['snapshot' => $snapshot])->save();
        $user = documentRequestUserWith(['view', 'review']);
        $user->forceFill(['name' => RULES_REVIEWER])->save();
        Sanctum::actingAs($user);

        $this->postJson(rulesUrl($request, 'items/12/stop-validation'))
            ->assertOk()
            ->assertJsonPath('data.pending_operation', 'stop_validation');

        Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::StopValidation
            && $job->arguments === ['item_id' => 12, 'reviewer_name' => RULES_REVIEWER]);
        $activity = rulesActivity('validation_stop_requested');
        expect($activity->causer_id)->toBe($user->id)->and($activity->properties)->not->toContain(RULES_REVIEWER);
    })->with(['uploaded', 'validating']);

    it('AC-005: stop-validation refuses an item not waiting for the AI, an unknown item and a closed request', function (string $path, ?string $status, string $field) {
        $request = rulesRequest(['status' => $status]);
        Sanctum::actingAs(documentRequestUserWith(['view', 'review']));

        $this->postJson(rulesUrl($request, $path))->assertUnprocessable()->assertJsonValidationErrors($field);
        Queue::assertNothingPushed();
    })->with([
        'item already rejected' => ['items/11/stop-validation', 'sent', 'document'],
        'unknown item' => ['items/999/stop-validation', 'sent', 'item'],
        'cancelled request' => ['items/12/stop-validation', 'cancelled', 'status'],
    ]);
});

describe('checklist endpoints', function () {
    beforeEach(function () {
        Queue::fake();
    });

    it('AC-006: open-requests counts only the draft, sent and in progress requests of the checklist', function () {
        [$checklist] = rulesChecklistWithRequests();
        actAsChecklistEditor();

        $this->getJson("/api/document-checklists/{$checklist->id}/open-requests")->assertOk()->assertJsonPath('data.count', 3);
    });

    it('AC-006: refresh-open-requests queues refresh_rules on each open request of a synced checklist', function () {
        [$checklist, $requests] = rulesChecklistWithRequests();
        actAsChecklistEditor();

        $this->postJson("/api/document-checklists/{$checklist->id}/refresh-open-requests")
            ->assertStatus(202)
            ->assertJsonPath('data.queued', 3);

        Queue::assertPushed(SyncDocumentRequestJob::class, 3);
        foreach (['draft', 'sent', 'in_progress'] as $status) {
            Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->documentRequestId === $requests[$status]->id
                && $job->operation === DocumentRequestOperation::RefreshRules);
            expect($requests[$status]->refresh()->pending_operation)->toBe(DocumentRequestOperation::RefreshRules);
        }
        expect(DB::table('activity_log')->where('event', 'rules_refresh_requested')->count())->toBe(3);
    });

    it('AC-006: refresh-open-requests answers 422 on checklist while the checklist is not sent', function (string $syncStatus) {
        [$checklist] = rulesChecklistWithRequests($syncStatus);
        actAsChecklistEditor();

        $this->postJson("/api/document-checklists/{$checklist->id}/refresh-open-requests")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('checklist');
        Queue::assertNothingPushed();
    })->with(['pending', 'failed']);

    it('AC-006: both endpoints need update on the checklist and document-requests.update', function () {
        [$checklist] = rulesChecklistWithRequests();

        actAsChecklistEditor(withRequestUpdate: false);
        $this->getJson("/api/document-checklists/{$checklist->id}/open-requests")->assertForbidden();
        $this->postJson("/api/document-checklists/{$checklist->id}/refresh-open-requests")->assertForbidden();

        Sanctum::actingAs(documentRequestUserWith(['update']));
        $this->getJson("/api/document-checklists/{$checklist->id}/open-requests")->assertForbidden();
        $this->postJson("/api/document-checklists/{$checklist->id}/refresh-open-requests")->assertForbidden();

        Queue::assertNothingPushed();
    });
});

describe('job', function () {
    beforeEach(function () {
        documentRequestConnection();
    });

    it('AC-002: refresh_rules posts without a body, replaces the snapshot and keeps the changed items', function (array $updated, array $expected) {
        $remote = documentRequestRemote(['status' => 'in_progress']);
        $remote['items'][0]['description'] = 'Regole corrette';
        Http::fake(['*' => Http::response(['data' => $remote, 'meta' => ['updated_items' => $updated]], 200)]);
        $request = rulesRequest(['delivery_status' => 'pending', 'pending_operation' => 'refresh_rules', 'rules_updated_item_ids' => [99]]);

        runRulesJob($request, DocumentRequestOperation::RefreshRules);

        Http::assertSent(fn (Request $sent) => $sent->method() === 'POST'
            && $sent->url() === DR_BASE_URL."/requests/{$request->id}/refresh-rules"
            && $sent->data() === []);
        $request->refresh();
        expect($request->rules_updated_item_ids)->toBe($expected)
            ->and($request->snapshot['items'][0]['description'])->toBe('Regole corrette')
            ->and($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Delivered)
            ->and($request->pending_operation)->toBeNull();
    })->with([
        'changed items' => [[11, 12], [11, 12]],
        'nothing to refresh' => [[], []],
    ]);

    it('AC-002: a 422 or a 409 of refresh_rules is final with the message', function (int $status, array $body, string $message) {
        Http::fake(['*' => Http::response($body, $status)]);
        $request = rulesRequest(['delivery_status' => 'pending', 'pending_operation' => 'refresh_rules']);

        $job = runRulesJob($request, DocumentRequestOperation::RefreshRules);

        $job->assertNotReleased();
        $request->refresh();
        expect($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Failed)
            ->and($request->delivery_error)->toBe($message)
            ->and(DB::table('document_request_sync_logs')->sole()->operation)->toBe('refresh_rules');
    })->with([
        '422 checklist' => [422, ['errors' => ['checklist' => ['La checklist e stata eliminata']]], 'La checklist e stata eliminata'],
        '409' => [409, ['message' => 'Richiesta non creata da QNet'], 'Richiesta non creata da QNet'],
    ]);

    it('AC-004: revalidate posts to the item endpoint and takes the 202 as a success', function () {
        $remote = documentRequestRemote(['status' => 'in_progress']);
        $remote['items'][0]['status'] = 'uploaded';
        Http::fake(['*' => Http::response(['data' => $remote], 202)]);
        $request = rulesRequest(['delivery_status' => 'pending', 'pending_operation' => 'revalidate']);

        runRulesJob($request, DocumentRequestOperation::Revalidate, ['item_id' => 11]);

        Http::assertSent(fn (Request $sent) => $sent->method() === 'POST'
            && $sent->url() === DR_BASE_URL."/requests/{$request->id}/items/11/revalidate"
            && $sent->data() === []);
        $request->refresh();
        expect($request->snapshot['items'][0]['status'])->toBe('uploaded')
            ->and($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Delivered)
            ->and(DB::table('document_request_sync_logs')->sole()->http_status)->toBe(202);
    });

    it('AC-004: AI validation disabled in ArchivioSmart fails with its message', function () {
        Http::fake(['*' => Http::response(['errors' => ['ai_validation_enabled' => ['Validazione AI disattivata']]], 422)]);
        $request = rulesRequest(['delivery_status' => 'pending', 'pending_operation' => 'revalidate']);

        runRulesJob($request, DocumentRequestOperation::Revalidate, ['item_id' => 11]);

        expect($request->refresh()->delivery_error)->toBe('Validazione AI disattivata');
    });

    it('AC-005: stop_validation posts the reviewer name, which never reaches the sync log', function () {
        $remote = documentRequestRemote(['status' => 'in_progress']);
        $remote['items'][1]['status'] = 'review';
        Http::fake(['*' => Http::response(['data' => $remote], 200)]);
        $request = rulesRequest(['delivery_status' => 'pending', 'pending_operation' => 'stop_validation']);

        runRulesJob($request, DocumentRequestOperation::StopValidation, ['item_id' => 12, 'reviewer_name' => RULES_REVIEWER]);

        Http::assertSent(fn (Request $sent) => $sent->url() === DR_BASE_URL."/requests/{$request->id}/items/12/stop-validation"
            && $sent->data() === ['reviewer_name' => RULES_REVIEWER]);
        expect($request->refresh()->snapshot['items'][1]['status'])->toBe('review')
            ->and(json_encode(DB::table('document_request_sync_logs')->get()))->not->toContain(RULES_REVIEWER)
            ->and(DB::table('document_request_sync_logs')->sole()->operation)->toBe('stop_validation');
    });
});

it('AC-007: a corrected prompt keeps the external_id of the item in the checklist payload', function () {
    $checklist = documentChecklistWithItems(['Documento A']);
    $item = $checklist->items()->sole();
    Queue::fake();
    Sanctum::actingAs(documentChecklistUserWith(['view', 'update']));

    $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload([
        'code' => $checklist->code,
        'items' => [documentChecklistItemPayload(['id' => $item->id, 'name' => 'Documento A', 'validation_prompt' => 'Prompt corretto'])],
    ]))->assertOk();

    $payload = app(DocumentChecklistPayloadBuilder::class)->build($checklist->fresh(['items', 'sections']));
    expect($payload['items'])->toHaveCount(1)
        ->and($payload['items'][0]['external_id'])->toBe((string) $item->id)
        ->and($payload['items'][0]['validation_prompt'])->toBe('Prompt corretto')
        ->and($payload['items'][0])->toHaveKey('validation_rules');
});
