<?php

use App\Enums\DocumentRequestDeliveryStatus;
use App\Enums\DocumentRequestOperation;
use App\Enums\DocumentRequestStatus;
use App\Jobs\SyncDocumentRequestJob;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
| Manual approve/reject of an item and early completion (spec 0429): AC-003,
| AC-004, AC-005, AC-006, AC-007, AC-008. Endpoints with a faked queue, then the
| job against a faked Http. The download is in DocumentRequestDownloadTest.
*/

uses(RefreshDatabase::class);

const REVIEW_NOTE = 'Nota riservata del revisore';
const REVIEW_REVIEWER = 'Mario Revisore';

function reviewRequestUrl(DocumentRequest $request, string $path = ''): string
{
    return "/api/document-requests/{$request->id}".($path === '' ? '' : "/{$path}");
}

/**
 * @param  array<string, mixed>  $arguments
 */
function runReviewJob(DocumentRequest $request, DocumentRequestOperation $operation, array $arguments = [], int $attempt = 1): SyncDocumentRequestJob
{
    $job = (new SyncDocumentRequestJob($request->id, $operation, true, $arguments))->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    app()->call([$job, 'handle']);

    return $job;
}

describe('endpoints', function () {
    beforeEach(function () {
        Queue::fake();
    });

    it('AC-003: exposes the files without checksum, current_document_id and the review and complete permissions', function () {
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['status' => 'in_progress']);
        Sanctum::actingAs(documentRequestUserWith(['view', 'review', 'update']));

        $response = $this->getJson(reviewRequestUrl($request))->assertOk();

        $response->assertJsonPath('data.items.0.current_document_id', 101)
            ->assertJsonPath('data.items.0.documents_count', 1)
            ->assertJsonPath('data.items.0.documents.0', [
                'id' => 101, 'slot' => 1, 'version' => 1, 'is_current' => true, 'status' => 'ko', 'status_reason' => 'Illeggibile',
                'original_name' => 'Carta identita.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2048,
                'created_at' => '2026-12-02T09:00:00+00:00',
            ])
            ->assertJsonPath('data.items.1.current_document_id', null)
            ->assertJsonPath('data.items.1.documents', [])
            ->assertJsonPath('data.permissions.review', true)
            ->assertJsonPath('data.permissions.complete', true);
        expect(json_encode($response->json()))->not->toContain('deadbeef')->not->toContain('sha256');
    });

    it('AC-003: permissions follow the permission and the state', function (array $abilities, string $status, bool $review, bool $complete) {
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['status' => $status]);
        Sanctum::actingAs(documentRequestUserWith(['view', ...$abilities]));

        $this->getJson(reviewRequestUrl($request))->assertOk()
            ->assertJsonPath('data.permissions.review', $review)
            ->assertJsonPath('data.permissions.complete', $complete);
    })->with([
        'review only, sent' => [['review'], 'sent', true, false],
        'update only, in progress' => [['update'], 'in_progress', false, true],
        'both, completed' => [['review', 'update'], 'completed', false, false],
        'both, cancelled' => [['review', 'update'], 'cancelled', false, false],
        'both, draft' => [['review', 'update'], 'draft', false, false],
    ]);

    it('AC-004: approve queues the job with the note and the name of the user, after the commit', function () {
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['status' => 'in_progress']);
        $user = documentRequestUserWith(['view', 'review']);
        $user->forceFill(['name' => REVIEW_REVIEWER])->save();
        Sanctum::actingAs($user);

        $this->postJson(reviewRequestUrl($request, 'items/11/approve'), ['note' => REVIEW_NOTE])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pending_operation', 'approve')
            ->assertJsonPath('data.delivery_status', 'pending');

        Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->documentRequestId === $request->id
            && $job->operation === DocumentRequestOperation::Approve
            && $job->arguments === ['item_id' => 11, 'note' => REVIEW_NOTE, 'reviewer_name' => REVIEW_REVIEWER]
            && $job->afterCommit === true);
    });

    it('AC-004: approve accepts a missing or empty note and a note of 1000 characters', function (?string $note) {
        $request = documentRequestWithFiles(documentRequestWorkOrder());
        Sanctum::actingAs(documentRequestUserWith(['view', 'review']));

        $this->postJson(reviewRequestUrl($request, 'items/11/approve'), $note === null ? [] : ['note' => $note])->assertOk();

        Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->arguments['note'] === ($note === '' ? null : $note));
    })->with([null, '', 'a note', fn () => str_repeat('n', 1000)]);

    it('AC-005: approve rejects a note over 1000 characters', function () {
        $request = documentRequestWithFiles(documentRequestWorkOrder());
        Sanctum::actingAs(documentRequestUserWith(['view', 'review']));

        $this->postJson(reviewRequestUrl($request, 'items/11/approve'), ['note' => str_repeat('n', 1001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');
        Queue::assertNothingPushed();
    });

    it('AC-005: reject requires the note, within 1000 characters', function (array $body) {
        $request = documentRequestWithFiles(documentRequestWorkOrder());
        Sanctum::actingAs(documentRequestUserWith(['view', 'review']));

        $this->postJson(reviewRequestUrl($request, 'items/11/reject'), $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');
        Queue::assertNothingPushed();
    })->with([
        'missing' => [[]],
        'null' => [['note' => null]],
        'empty' => [['note' => '']],
        'too long' => [fn () => ['note' => str_repeat('n', 1001)]],
    ]);

    it('AC-005: reject queues the job with the note and the name of the user', function () {
        $request = documentRequestWithFiles(documentRequestWorkOrder());
        $user = documentRequestUserWith(['view', 'review']);
        Sanctum::actingAs($user);

        $this->postJson(reviewRequestUrl($request, 'items/11/reject'), ['note' => REVIEW_NOTE])
            ->assertOk()
            ->assertJsonPath('data.pending_operation', 'reject');

        Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::Reject
            && $job->arguments === ['item_id' => 11, 'note' => REVIEW_NOTE, 'reviewer_name' => $user->name]);
    });

    it('AC-006: approve and reject answer 422 on status when the request is not sent or in progress', function (string $action, ?string $status) {
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['status' => $status]);
        Sanctum::actingAs(documentRequestUserWith(['view', 'review']));

        $this->postJson(reviewRequestUrl($request, "items/11/{$action}"), ['note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        Queue::assertNothingPushed();
        expect($request->refresh()->pending_operation)->toBeNull();
    })->with(fn () => collect(['approve', 'reject'])->crossJoin(['completed', 'expired', 'cancelled', 'draft', null])->all());

    it('AC-006: an item outside the snapshot answers 422 on item, an item without file 422 on document', function (string $action) {
        $request = documentRequestWithFiles(documentRequestWorkOrder());
        Sanctum::actingAs(documentRequestUserWith(['view', 'review']));

        $this->postJson(reviewRequestUrl($request, "items/999/{$action}"), ['note' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('item');
        $this->postJson(reviewRequestUrl($request, "items/12/{$action}"), ['note' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('document');
        Queue::assertNothingPushed();
    })->with(['approve', 'reject']);

    it('AC-006: review is a separate permission and needs view on the work order', function (string $action) {
        $request = documentRequestWithFiles(documentRequestWorkOrder());

        Sanctum::actingAs(documentRequestUserWith(['view', 'update']));
        $this->postJson(reviewRequestUrl($request, "items/11/{$action}"), ['note' => 'x'])->assertForbidden();
        $this->postJson(reviewRequestUrl($request, "items/11/{$action}"), [])->assertForbidden();

        Sanctum::actingAs(documentRequestUserWith(['view', 'review'], canViewWorkOrders: false));
        $this->postJson(reviewRequestUrl($request, "items/11/{$action}"), ['note' => 'x'])->assertForbidden();

        Queue::assertNothingPushed();
    })->with(['approve', 'reject']);

    it('AC-007: complete queues the operation on a sent or in progress request', function (string $status) {
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['status' => $status]);
        Sanctum::actingAs(documentRequestUserWith(['view', 'update']));

        $this->postJson(reviewRequestUrl($request, 'complete'))
            ->assertOk()
            ->assertJsonPath('data.pending_operation', 'complete')
            ->assertJsonPath('data.delivery_status', 'pending');

        Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::Complete && $job->afterCommit === true);
    })->with(['sent', 'in_progress']);

    it('AC-007: complete on an already completed request answers 200 without a job', function () {
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['status' => 'completed']);
        Sanctum::actingAs(documentRequestUserWith(['update']));

        $this->postJson(reviewRequestUrl($request, 'complete'))->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.pending_operation', null);
        Queue::assertNothingPushed();
    });

    it('AC-007: complete answers 422 on status for a draft, expired, cancelled or never answered request', function (?string $status) {
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['status' => $status]);
        Sanctum::actingAs(documentRequestUserWith(['update']));

        $this->postJson(reviewRequestUrl($request, 'complete'))->assertUnprocessable()->assertJsonValidationErrors('status');
        Queue::assertNothingPushed();
    })->with(['draft', 'expired', 'cancelled', null]);

    it('AC-007: complete answers 403 without update, even with review', function () {
        $request = documentRequestWithFiles(documentRequestWorkOrder());
        Sanctum::actingAs(documentRequestUserWith(['view', 'review']));

        $this->postJson(reviewRequestUrl($request, 'complete'))->assertForbidden();
        Queue::assertNothingPushed();
    });
});

describe('job', function () {
    beforeEach(function () {
        documentRequestConnection();
    });

    it('AC-004: approve posts note and reviewer_name, replaces the snapshot and delivers', function () {
        $remote = documentRequestRemote(['status' => 'in_progress', 'items_satisfied_count' => 1]);
        $remote['items'][0]['status'] = 'approved_manually';
        Http::fake(['*' => Http::response(['data' => $remote], 200)]);
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['status' => 'sent', 'delivery_status' => 'pending', 'pending_operation' => 'approve']);

        runReviewJob($request, DocumentRequestOperation::Approve, ['item_id' => 11, 'note' => REVIEW_NOTE, 'reviewer_name' => REVIEW_REVIEWER]);

        Http::assertSent(fn (Request $sent) => $sent->method() === 'POST'
            && $sent->url() === DR_BASE_URL."/requests/{$request->id}/items/11/approve"
            && $sent->data() === ['note' => REVIEW_NOTE, 'reviewer_name' => REVIEW_REVIEWER]
            && $sent->hasHeader('Authorization', 'Bearer '.DR_TOKEN));
        $request->refresh();
        expect($request->snapshot['items'][0]['status'])->toBe('approved_manually')
            ->and($request->status)->toBe(DocumentRequestStatus::InProgress)
            ->and($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Delivered)
            ->and($request->pending_operation)->toBeNull();
    });

    it('AC-005: reject posts the note and the reviewer name to the reject endpoint', function () {
        Http::fake(['*' => Http::response(['data' => documentRequestRemote()], 200)]);
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['delivery_status' => 'pending', 'pending_operation' => 'reject']);

        runReviewJob($request, DocumentRequestOperation::Reject, ['item_id' => 11, 'note' => REVIEW_NOTE, 'reviewer_name' => REVIEW_REVIEWER]);

        Http::assertSent(fn (Request $sent) => $sent->url() === DR_BASE_URL."/requests/{$request->id}/items/11/reject"
            && $sent->data() === ['note' => REVIEW_NOTE, 'reviewer_name' => REVIEW_REVIEWER]);
    });

    it('AC-007: complete posts without a body and stores status completed', function () {
        Http::fake(['*' => Http::response(['data' => documentRequestRemote(['status' => 'completed', 'completed_at' => '2026-12-05T10:00:00+00:00'])], 200)]);
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['delivery_status' => 'pending', 'pending_operation' => 'complete']);

        runReviewJob($request, DocumentRequestOperation::Complete);

        Http::assertSent(fn (Request $sent) => $sent->method() === 'POST'
            && $sent->url() === DR_BASE_URL."/requests/{$request->id}/complete"
            && $sent->data() === []);
        $request->refresh();
        expect($request->status)->toBe(DocumentRequestStatus::Completed)
            ->and($request->completed_at)->not->toBeNull()
            ->and($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Delivered);
    });

    it('AC-008: a 422 is final and shows the ArchivioSmart message, a 404 says the action is not released', function (int $status, array $body, string $message) {
        Http::fake(['*' => Http::response($body, $status)]);
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['delivery_status' => 'pending', 'pending_operation' => 'approve']);

        $job = runReviewJob($request, DocumentRequestOperation::Approve, ['item_id' => 11, 'note' => REVIEW_NOTE, 'reviewer_name' => REVIEW_REVIEWER]);

        $request->refresh();
        expect($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Failed)
            ->and($request->delivery_error)->toBe($message);
        $job->assertNotReleased();
        $log = DB::table('document_request_sync_logs')->sole();
        expect($log->outcome)->toBe('failed')->and($log->http_status)->toBe($status)->and($log->operation)->toBe('approve');
    })->with([
        '422' => [422, ['errors' => ['status' => ['La richiesta e chiusa']]], 'La richiesta e chiusa'],
        '404' => [404, ['message' => 'Not found'], fn () => __('archivio_smart.request_action_unavailable')],
    ]);

    it('AC-008: 5xx and 429 are retried like the other operations', function (int $status) {
        Http::fake(['*' => Http::response([], $status, ['Retry-After' => '30'])]);
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['delivery_status' => 'pending', 'pending_operation' => 'complete']);

        $job = runReviewJob($request, DocumentRequestOperation::Complete);

        $job->assertReleased();
        expect($request->refresh()->delivery_status)->toBe(DocumentRequestDeliveryStatus::Pending)
            ->and(DB::table('document_request_sync_logs')->sole()->outcome)->toBe('retrying');
    })->with([500, 503, 429]);

    it('AC-008: neither the note nor the reviewer name reach the logs, the sync log or the activity log', function (int $status) {
        Http::fake(['*' => Http::response(['errors' => ['status' => ['Rifiutato']]], $status)]);
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message.json_encode($event->context);
        });
        $request = documentRequestWithFiles(documentRequestWorkOrder(), ['delivery_status' => 'pending', 'pending_operation' => 'reject']);
        $arguments = ['item_id' => 11, 'note' => REVIEW_NOTE, 'reviewer_name' => REVIEW_REVIEWER];

        runReviewJob($request, DocumentRequestOperation::Reject, $arguments, 1);
        (new SyncDocumentRequestJob($request->id, DocumentRequestOperation::Reject, true, $arguments))->failed(new RuntimeException('boom'));

        $dump = json_encode([DB::table('document_request_sync_logs')->get(), DB::table('activity_log')->get(), $logged]);
        expect($dump)->not->toContain(REVIEW_NOTE)->not->toContain(REVIEW_REVIEWER)->not->toContain(DR_TOKEN);
    })->with([422, 404, 503]);
});
