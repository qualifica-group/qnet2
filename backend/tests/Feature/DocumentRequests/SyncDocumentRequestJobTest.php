<?php

use App\Enums\DocumentRequestDeliveryStatus;
use App\Enums\DocumentRequestOperation;
use App\Enums\DocumentRequestStatus;
use App\Jobs\SyncDocumentRequestJob;
use App\Models\DocumentRequest;
use App\Services\ArchivioSmart\ArchivioSmartClient;
use App\Services\DocumentRequests\DocumentRequestPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/*
| SyncDocumentRequestJob (spec 0428): AC-001 (body), AC-002, AC-003, AC-007 (body),
| AC-008, AC-009, AC-010, AC-016 (token never in logs). Http is always faked.
*/

uses(RefreshDatabase::class);

function runDocumentRequestJob(DocumentRequest $request, DocumentRequestOperation $operation, int $attempt = 1, bool $send = true): SyncDocumentRequestJob
{
    $job = (new SyncDocumentRequestJob($request->id, $operation, $send))->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    app()->call([$job, 'handle']);

    return $job;
}

/**
 * A request not yet answered by ArchivioSmart, as the API leaves it.
 *
 * @param  array<string, mixed>  $attributes
 */
function newDocumentRequest(array $attributes = []): DocumentRequest
{
    return DocumentRequest::factory()->create([
        'work_order_id' => documentRequestWorkOrder()->id,
        'checklist_code' => 'APERTURA',
        'due_date' => '2026-12-31',
        'recipient_email' => 'destinatario@example.test',
        'message' => 'Carichi i documenti',
        'delivery_status' => 'pending',
        'pending_operation' => 'upsert',
        ...$attributes,
    ]);
}

it('AC-001: sends the creation PUT with a string project id, the checklist code, send true and no customer id', function () {
    documentRequestConnection();
    Http::fake(['*' => Http::response(['data' => documentRequestRemote()], 201)]);
    $request = newDocumentRequest();

    runDocumentRequestJob($request, DocumentRequestOperation::Upsert);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $sent) use ($request) {
        $body = $sent->data();

        return $sent->method() === 'PUT'
            && $sent->url() === DR_BASE_URL.'/requests/'.$request->id
            && $sent->hasHeader('Authorization', 'Bearer '.DR_TOKEN)
            && $body['project_external_id'] === (string) $request->work_order_id
            && is_string($body['project_external_id'])
            && $body['checklist_code'] === 'APERTURA'
            && $body['send'] === true
            && $body['due_date'] === '2026-12-31'
            && $body['recipient_email'] === 'destinatario@example.test'
            && $body['message'] === 'Carichi i documenti'
            && ! array_key_exists('customer_external_id', $body);
    });
});

it('AC-001: send false is carried in the body', function () {
    documentRequestConnection();
    Http::fake(['*' => Http::response(['data' => documentRequestRemote(['status' => 'draft'])], 201)]);

    runDocumentRequestJob(newDocumentRequest(), DocumentRequestOperation::Upsert, send: false);

    Http::assertSent(fn (Request $sent) => $sent->data()['send'] === false);
});

it('AC-002: the 201 response is saved and the request is delivered', function () {
    documentRequestConnection();
    Http::fake(['*' => Http::response(['data' => documentRequestRemote([
        'items_count' => 2, 'items_uploaded_count' => 1, 'items_satisfied_count' => 1,
        'link_expires_at' => '2026-12-31T10:00:00+00:00',
    ])], 201)]);
    $request = newDocumentRequest();

    runDocumentRequestJob($request, DocumentRequestOperation::Upsert);

    $request->refresh();
    expect($request->status)->toBe(DocumentRequestStatus::Sent)
        ->and($request->title)->toBe('Apertura pratica')
        ->and($request->items_count)->toBe(2)
        ->and($request->items_uploaded_count)->toBe(1)
        ->and($request->items_satisfied_count)->toBe(1)
        ->and($request->snapshot['sections'])->toBe([['name' => 'Identita', 'description' => null]])
        ->and($request->snapshot['items'])->toHaveCount(2)
        ->and($request->snapshot['items'][1]['documents'])->toHaveCount(1)
        ->and($request->sent_at->toDateString())->toBe('2026-12-01')
        ->and($request->link_expires_at->toDateString())->toBe('2026-12-31')
        ->and($request->remote_updated_at)->not->toBeNull()
        ->and($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Delivered)
        ->and($request->pending_operation)->toBeNull()
        ->and($request->delivery_error)->toBeNull()
        ->and($request->due_date->toDateString())->toBe('2026-12-31')
        ->and($request->syncLogs()->sole()->outcome->value)->toBe('success');
});

it('AC-002: repeating the job with a 200 answer creates no other row and replaces the snapshot as a whole', function () {
    documentRequestConnection();
    $request = newDocumentRequest();
    Http::fake(['*' => Http::sequence()
        ->push(['data' => documentRequestRemote()], 201)
        ->push(['data' => documentRequestRemote(['items' => [], 'items_count' => 0, 'status' => 'in_progress'])], 200)]);

    runDocumentRequestJob($request, DocumentRequestOperation::Upsert);
    runDocumentRequestJob($request, DocumentRequestOperation::Upsert);

    $request->refresh();
    expect(DocumentRequest::query()->count())->toBe(1)
        ->and($request->snapshot['items'])->toBe([])
        ->and($request->status)->toBe(DocumentRequestStatus::InProgress)
        ->and($request->syncLogs()->count())->toBe(2);
});

it('AC-007: the edit PUT sends only due_date, recipient_email, message and send', function () {
    documentRequestConnection();
    Http::fake(['*' => Http::response(['data' => documentRequestRemote()], 200)]);
    $request = newDocumentRequest(['status' => 'sent']);

    runDocumentRequestJob($request, DocumentRequestOperation::Upsert, send: false);

    Http::assertSent(fn (Request $sent) => $sent->method() === 'PUT'
        && $sent->url() === DR_BASE_URL.'/requests/'.$request->id
        && $sent->data() === [
            'due_date' => '2026-12-31',
            'recipient_email' => 'destinatario@example.test',
            'message' => 'Carichi i documenti',
            'send' => false,
        ]);
});

it('AC-003: a 422 is final, shows the field message and is not retried', function (array $errors, string $expected) {
    documentRequestConnection();
    Http::fake(['*' => Http::response(['message' => 'The given data was invalid.', 'errors' => $errors], 422)]);
    $request = newDocumentRequest();

    $job = runDocumentRequestJob($request, DocumentRequestOperation::Upsert);

    $job->assertNotReleased();
    $request->refresh();
    expect($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Failed)
        ->and($request->delivery_error)->toBe($expected)
        ->and($request->status)->toBeNull()
        ->and($request->syncLogs()->sole()->outcome->value)->toBe('failed')
        ->and($request->syncLogs()->sole()->http_status)->toBe(422);
})->with([
    'customer_external_id' => [['customer_external_id' => ['Il cliente non esiste.']], 'Il cliente non esiste.'],
    'checklist_code' => [['checklist_code' => ['Checklist non trovata.']], 'Checklist non trovata.'],
]);

it('AC-003: a 503 or 500 releases the job with the backoff and the request stays pending', function (int $status) {
    documentRequestConnection();
    Http::fake(['*' => Http::response([], $status)]);
    $request = newDocumentRequest();

    $job = runDocumentRequestJob($request, DocumentRequestOperation::Upsert, 2);

    $job->assertReleased(30);
    $request->refresh();
    expect($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Pending)
        ->and($request->syncLogs()->sole()->outcome->value)->toBe('retrying');
})->with([503, 500]);

it('AC-003: a 5xx on the last attempt fails the request', function () {
    documentRequestConnection();
    Http::fake(['*' => Http::response([], 503)]);
    $request = newDocumentRequest();

    $job = runDocumentRequestJob($request, DocumentRequestOperation::Upsert, (int) config('archivio-smart.tries'));

    $job->assertNotReleased();
    expect($request->fresh()->delivery_status)->toBe(DocumentRequestDeliveryStatus::Failed);
});

it('AC-003: a 429 releases the job after the Retry-After seconds', function () {
    documentRequestConnection();
    Http::fake(['*' => Http::response([], 429, ['Retry-After' => '7'])]);
    $request = newDocumentRequest();

    $job = runDocumentRequestJob($request, DocumentRequestOperation::Upsert);

    $job->assertReleased(7);
    expect($request->fresh()->delivery_status)->toBe(DocumentRequestDeliveryStatus::Pending);
});

it('AC-003: a connection error is retried without exposing its message', function () {
    documentRequestConnection();
    Http::fake(fn () => throw new ConnectionException('cURL error 6: could not resolve host archivio.test'));
    $request = newDocumentRequest();

    $job = runDocumentRequestJob($request, DocumentRequestOperation::Upsert);

    $job->assertReleased(10);
    expect(json_encode($request->syncLogs()->get()))->not->toContain('cURL');
});

it('a 401 and a 409 are final; the 409 shows the message of ArchivioSmart', function () {
    documentRequestConnection();
    $request = newDocumentRequest();

    Http::fake(['*' => Http::sequence()
        ->push([], 401)
        ->push(['message' => 'Richiesta gia presente con altro progetto.'], 409)]);
    runDocumentRequestJob($request, DocumentRequestOperation::Upsert)->assertNotReleased();
    expect($request->fresh()->delivery_status)->toBe(DocumentRequestDeliveryStatus::Failed)
        ->and($request->fresh()->delivery_error)->toBe(__('archivio_smart.unauthorized'));

    runDocumentRequestJob($request, DocumentRequestOperation::Upsert)->assertNotReleased();
    expect($request->fresh()->delivery_error)->toBe('Richiesta gia presente con altro progetto.');
});

it('fails the request when the connection is not configured, without any call', function () {
    Http::fake();
    $request = newDocumentRequest();

    runDocumentRequestJob($request, DocumentRequestOperation::Upsert);

    Http::assertNothingSent();
    expect($request->fresh()->delivery_status)->toBe(DocumentRequestDeliveryStatus::Failed)
        ->and($request->fresh()->delivery_error)->toBe(__('archivio_smart.not_configured'));
});

it('AC-008: remind posts to /remind and saves the answer', function () {
    documentRequestConnection();
    Http::fake(['*' => Http::response(['data' => documentRequestRemote(['last_reminded_at' => '2026-12-05T09:00:00+00:00'])], 200)]);
    $request = newDocumentRequest(['status' => 'sent', 'pending_operation' => 'remind']);

    runDocumentRequestJob($request, DocumentRequestOperation::Remind);

    Http::assertSent(fn (Request $sent) => $sent->method() === 'POST' && $sent->url() === DR_BASE_URL.'/requests/'.$request->id.'/remind');
    $request->refresh();
    expect($request->last_reminded_at->toDateString())->toBe('2026-12-05')
        ->and($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Delivered)
        ->and($request->syncLogs()->sole()->operation)->toBe(DocumentRequestOperation::Remind);
});

it('AC-009: cancel posts to /cancel and takes the status from the answer', function () {
    documentRequestConnection();
    Http::fake(['*' => Http::response(['data' => documentRequestRemote(['status' => 'cancelled', 'cancelled_at' => '2026-12-06T09:00:00+00:00'])], 200)]);
    $request = newDocumentRequest(['status' => 'sent', 'pending_operation' => 'cancel']);

    runDocumentRequestJob($request, DocumentRequestOperation::Cancel);

    Http::assertSent(fn (Request $sent) => $sent->method() === 'POST' && $sent->url() === DR_BASE_URL.'/requests/'.$request->id.'/cancel');
    $request->refresh();
    expect($request->status)->toBe(DocumentRequestStatus::Cancelled)
        ->and($request->cancelled_at->toDateString())->toBe('2026-12-06');
});

it('AC-010: refresh gets the request and replaces the whole snapshot', function () {
    documentRequestConnection();
    $remote = documentRequestRemote(['items_uploaded_count' => 1]);
    $remote['items'][0]['status'] = 'ok';
    $remote['sections'] = [['name' => 'Nuovo gruppo', 'description' => 'Descrizione']];
    Http::fake(['*' => Http::response(['data' => $remote], 200)]);
    $request = documentRequestFor(documentRequestWorkOrder(), ['delivery_status' => 'pending', 'pending_operation' => 'refresh']);

    runDocumentRequestJob($request, DocumentRequestOperation::Refresh);

    Http::assertSent(fn (Request $sent) => $sent->method() === 'GET' && $sent->url() === DR_BASE_URL.'/requests/'.$request->id);
    $request->refresh();
    expect($request->snapshot['items'][0]['status'])->toBe('ok')
        ->and($request->snapshot['sections'])->toBe([['name' => 'Nuovo gruppo', 'description' => 'Descrizione']])
        ->and($request->items_uploaded_count)->toBe(1)
        ->and($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Delivered);
});

it('stays pending while a later operation of the same request is still waiting', function () {
    documentRequestConnection();
    Http::fake(['*' => Http::response(['data' => documentRequestRemote()], 200)]);
    $request = newDocumentRequest(['status' => 'sent', 'pending_operation' => 'remind']);

    runDocumentRequestJob($request, DocumentRequestOperation::Upsert);

    $request->refresh();
    expect($request->delivery_status)->toBe(DocumentRequestDeliveryStatus::Pending)
        ->and($request->pending_operation)->toBe(DocumentRequestOperation::Remind);
});

it('uses a WithoutOverlapping middleware keyed on the request', function () {
    $middleware = (new SyncDocumentRequestJob(5, DocumentRequestOperation::Upsert))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe('5');
});

it('does nothing for a request that no longer exists', function () {
    documentRequestConnection();
    Http::fake();
    $request = newDocumentRequest();
    $id = $request->id;
    $request->delete();

    (new SyncDocumentRequestJob($id, DocumentRequestOperation::Upsert))->handle(app(ArchivioSmartClient::class), app(DocumentRequestPayloadBuilder::class));

    Http::assertNothingSent();
});

it('AC-016: neither the token nor the recipient nor the message reach the logs, the sync log or the activity log', function (int $status) {
    documentRequestConnection();
    Http::fake(['*' => Http::response(['errors' => ['message' => ['Errore sul campo']]], $status)]);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.json_encode($event->context);
    });
    $request = newDocumentRequest();

    runDocumentRequestJob($request, DocumentRequestOperation::Upsert, 1);
    (new SyncDocumentRequestJob($request->id, DocumentRequestOperation::Upsert))->failed(new RuntimeException('boom'));

    $dump = json_encode([
        DB::table('document_request_sync_logs')->get(),
        DB::table('document_requests')->get(),
        DB::table('activity_log')->get(),
        $logged,
    ]);
    expect($dump)->not->toContain(DR_TOKEN)
        ->and(json_encode($logged))->not->toContain('destinatario@example.test')
        ->and(json_encode($logged))->not->toContain('Carichi i documenti')
        ->and(json_encode(DB::table('activity_log')->get()))->not->toContain('destinatario@example.test');
})->with([422, 503, 401]);
