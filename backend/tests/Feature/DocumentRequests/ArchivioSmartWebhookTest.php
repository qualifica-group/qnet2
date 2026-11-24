<?php

use App\Enums\ArchivioSmartWebhookEventStatus;
use App\Enums\DocumentRequestOperation;
use App\Enums\DocumentRequestStatus;
use App\Jobs\ProcessArchivioSmartWebhookJob;
use App\Jobs\SyncDocumentRequestJob;
use App\Models\ArchivioSmartSetting;
use App\Models\ArchivioSmartWebhookEvent;
use App\Models\DocumentRequest;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
| Inbound ArchivioSmart webhook (spec 0428): AC-011 .. AC-016. The queue runs
| synchronously unless a test fakes it; no network call is made.
*/

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function webhookBody(string $event, DocumentRequest|string|null $request, array $data = [], string $occurredAt = '2026-12-02T10:00:00+00:00'): array
{
    $externalId = $request instanceof DocumentRequest ? (string) $request->id : $request;

    return [
        'event' => $event,
        'occurred_at' => $occurredAt,
        'data' => [
            'request' => ['external_id' => $externalId, 'status' => 'in_progress'],
            ...$data,
        ],
    ];
}

function webhookRequest(): DocumentRequest
{
    return documentRequestFor(documentRequestWorkOrder(), ['status' => 'sent', 'delivery_status' => 'delivered']);
}

it('AC-011: a valid signature answers 202 and queues the processing job', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake();
    $request = webhookRequest();

    postArchivioSmartWebhook($this, webhookBody('document.uploaded', $request, ['item' => ['id' => 11]]), 'document.uploaded', 'evt-1')
        ->assertStatus(202)
        ->assertExactJson(['success' => true]);

    Queue::assertPushed(ProcessArchivioSmartWebhookJob::class, 1);
    expect(ArchivioSmartWebhookEvent::query()->sole())
        ->event_id->toBe('evt-1')
        ->event->toBe('document.uploaded')
        ->status->toBe(ArchivioSmartWebhookEventStatus::Received);
});

it('AC-011: an altered or missing signature answers 401 without a job nor an event row', function (?string $signature) {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake();
    $request = webhookRequest();

    $headers = $signature === null ? ['Signature' => ''] : ['Signature' => $signature];
    postArchivioSmartWebhook($this, webhookBody('document.uploaded', $request, ['item' => ['id' => 11]]), 'document.uploaded', 'evt-1', $headers)
        ->assertUnauthorized();

    Queue::assertNothingPushed();
    expect(ArchivioSmartWebhookEvent::query()->count())->toBe(0);
})->with([
    'wrong digest' => [str_repeat('0', 64)],
    'empty' => [null],
]);

it('AC-011: a call without the Signature header answers 401', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake();

    $this->postJson('/api/webhooks/archivio-smart', webhookBody('ping', null), ['X-Archiviosmart-Event' => 'ping', 'X-Archiviosmart-Event-Id' => 'evt-1'])
        ->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('AC-011: a body signed with another secret or altered after signing is refused', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake();
    $request = webhookRequest();
    $body = webhookBody('document.uploaded', $request, ['item' => ['id' => 11]]);

    postArchivioSmartWebhook($this, $body, 'document.uploaded', 'evt-1', secret: 'whsec_another_secret')->assertUnauthorized();

    $signed = hash_hmac('sha256', json_encode($body), DR_WEBHOOK_SECRET);
    postArchivioSmartWebhook($this, [...$body, 'event' => 'request.completed'], 'request.completed', 'evt-2', ['Signature' => $signed])->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('AC-011: without a configured secret the endpoint answers 404', function (bool $withConnection) {
    Queue::fake();
    if ($withConnection) {
        documentRequestConnection();
    }
    $request = webhookRequest();

    postArchivioSmartWebhook($this, webhookBody('ping', $request), 'ping', 'evt-1')->assertNotFound();

    Queue::assertNothingPushed();
    expect(ArchivioSmartWebhookEvent::query()->count())->toBe(0);
})->with([
    'connection without secret' => [true],
    'no connection' => [false],
]);

it('AC-011: the endpoint needs no login and has no throttle', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake();

    foreach (range(1, 30) as $number) {
        postArchivioSmartWebhook($this, webhookBody('ping', null), 'ping', "evt-{$number}")->assertStatus(202);
    }
});

it('AC-012: the same event id is processed once', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    $request = webhookRequest();
    $body = webhookBody('document.validated', $request, [
        'item' => ['id' => 11],
        'validation' => ['item_status' => 'ko', 'reason_for_customer' => 'Illeggibile'],
    ]);

    postArchivioSmartWebhook($this, $body, 'document.validated', 'evt-dup')->assertStatus(202);
    expect($request->fresh()->snapshot['items'][0]['status'])->toBe('ko');

    // A second delivery must not touch the item again: put it back and resend.
    $snapshot = $request->fresh()->snapshot;
    $snapshot['items'][0]['status'] = 'pending';
    $request->forceFill(['snapshot' => $snapshot])->save();

    postArchivioSmartWebhook($this, $body, 'document.validated', 'evt-dup')->assertStatus(202);

    expect(ArchivioSmartWebhookEvent::query()->where('event_id', 'evt-dup')->count())->toBe(1)
        ->and($request->fresh()->snapshot['items'][0]['status'])->toBe('pending');
});

it('AC-012: a duplicate does not queue a second job', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake();
    $body = webhookBody('ping', null);

    postArchivioSmartWebhook($this, $body, 'ping', 'evt-dup')->assertStatus(202);
    postArchivioSmartWebhook($this, $body, 'ping', 'evt-dup')->assertStatus(202);

    Queue::assertPushed(ProcessArchivioSmartWebhookJob::class, 1);
});

it('AC-012: when queueing fails the event is not recorded, so the retried delivery is processed', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    $this->mock(Dispatcher::class)->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue down'));

    postArchivioSmartWebhook($this, webhookBody('ping', null), 'ping', 'evt-lost')->assertStatus(500);

    expect(ArchivioSmartWebhookEvent::query()->where('event_id', 'evt-lost')->exists())->toBeFalse();
});

it('AC-013: document.uploaded sets the item to uploaded', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake([SyncDocumentRequestJob::class]);
    $request = webhookRequest();

    postArchivioSmartWebhook($this, webhookBody('document.uploaded', $request, ['item' => ['id' => 12]]), 'document.uploaded', 'evt-up')->assertStatus(202);

    $items = $request->fresh()->snapshot['items'];
    expect($items[1]['status'])->toBe('uploaded')
        ->and($items[0]['status'])->toBe('pending')
        ->and(ArchivioSmartWebhookEvent::query()->sole()->status)->toBe(ArchivioSmartWebhookEventStatus::Processed);
});

it('AC-013: document.validated with ko sets the status, the reason and the latest validation of the item', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake([SyncDocumentRequestJob::class]);
    $request = webhookRequest();

    postArchivioSmartWebhook($this, webhookBody('document.validated', $request, [
        'item' => ['id' => 11],
        'validation' => ['item_status' => 'ko', 'reason_for_customer' => 'Documento scaduto', 'outcome' => 'ko', 'decided_by' => 'automatic', 'created_at' => '2026-12-02T09:59:00+00:00'],
    ]), 'document.validated', 'evt-val')->assertStatus(202);

    $item = $request->fresh()->snapshot['items'][0];
    expect($item['status'])->toBe('ko')
        ->and($item['status_reason'])->toBe('Documento scaduto')
        ->and($item['latest_validation'])->toBe([
            'outcome' => 'ko',
            'reason_for_customer' => 'Documento scaduto',
            'decided_by' => 'automatic',
            'created_at' => '2026-12-02T09:59:00+00:00',
        ]);

    Sanctum::actingAs(documentRequestUserWith(['view']));
    $this->getJson('/api/document-requests/'.$request->id)
        ->assertJsonPath('data.items.0.status', 'ko')
        ->assertJsonPath('data.items.0.status_reason', 'Documento scaduto')
        ->assertJsonPath('data.items.0.latest_validation.reason_for_customer', 'Documento scaduto');
});

it('AC-013: request.completed sets status and completed_at, request.expired sets expired', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    $completed = webhookRequest();
    $expired = webhookRequest();

    postArchivioSmartWebhook($this, webhookBody('request.completed', $completed, [], '2026-12-03T10:00:00+00:00'), 'request.completed', 'evt-c')->assertStatus(202);
    postArchivioSmartWebhook($this, webhookBody('request.expired', $expired, [], '2026-12-04T10:00:00+00:00'), 'request.expired', 'evt-e')->assertStatus(202);

    $completed->refresh();
    $expired->refresh();
    expect($completed->status)->toBe(DocumentRequestStatus::Completed)
        ->and($completed->completed_at->toDateString())->toBe('2026-12-03')
        ->and($expired->status)->toBe(DocumentRequestStatus::Expired)
        ->and($expired->expired_at->toDateString())->toBe('2026-12-04')
        ->and($expired->completed_at)->toBeNull();
});

it('AC-013: an item event updates the request status carried in data.request and queues a refresh for the counters and the files', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake([SyncDocumentRequestJob::class]);
    $request = webhookRequest();

    // The real document.* payload carries data.request without counters and the item without its file list.
    postArchivioSmartWebhook($this, webhookBody('document.uploaded', $request, ['item' => ['id' => 11]]), 'document.uploaded', 'evt-s')->assertStatus(202);

    $request->refresh();
    expect($request->status)->toBe(DocumentRequestStatus::InProgress)
        ->and($request->snapshot['items'][0]['status'])->toBe('uploaded')
        ->and($request->last_event_at->toDateString())->toBe('2026-12-02')
        ->and($request->pending_operation)->toBe(DocumentRequestOperation::Refresh);
    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->documentRequestId === $request->id && $job->operation === DocumentRequestOperation::Refresh);
});

it('AC-014: an older event does not roll the request status back', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => 'completed', 'remote_updated_at' => '2026-12-05 10:00:00']);

    postArchivioSmartWebhook($this, webhookBody('request.completed', $request, [], '2026-12-04T10:00:00+00:00'), 'request.completed', 'evt-old-1')->assertStatus(202);
    postArchivioSmartWebhook($this, webhookBody('request.expired', $request, [], '2026-12-04T11:00:00+00:00'), 'request.expired', 'evt-old-2')->assertStatus(202);

    $request->refresh();
    expect($request->status)->toBe(DocumentRequestStatus::Completed)
        ->and($request->expired_at)->toBeNull()
        ->and($request->remote_updated_at->toDateString())->toBe('2026-12-05');
});

it('AC-014: an older document event leaves the item untouched and queues a refresh', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake([SyncDocumentRequestJob::class]);
    $request = documentRequestFor(documentRequestWorkOrder(), ['status' => 'sent', 'remote_updated_at' => '2026-12-05 10:00:00']);

    postArchivioSmartWebhook($this, webhookBody('document.validated', $request, [
        'item' => ['id' => 11],
        'validation' => ['item_status' => 'ko', 'reason_for_customer' => 'Vecchio'],
    ], '2026-12-04T10:00:00+00:00'), 'document.validated', 'evt-old')->assertStatus(202);

    $request->refresh();
    expect($request->snapshot['items'][0]['status'])->toBe('pending')
        ->and($request->pending_operation)->toBe(DocumentRequestOperation::Refresh);
    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->documentRequestId === $request->id && $job->operation === DocumentRequestOperation::Refresh);
});

it('AC-014: an event for an item missing from the snapshot queues a refresh', function () {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake([SyncDocumentRequestJob::class]);
    $request = webhookRequest();

    postArchivioSmartWebhook($this, webhookBody('document.uploaded', $request, ['item' => ['id' => 999]]), 'document.uploaded', 'evt-new-item')->assertStatus(202);

    Queue::assertPushed(SyncDocumentRequestJob::class, fn (SyncDocumentRequestJob $job) => $job->operation === DocumentRequestOperation::Refresh);
    expect(ArchivioSmartWebhookEvent::query()->sole()->status)->toBe(ArchivioSmartWebhookEventStatus::Processed);
});

it('AC-015: an event with a null or unknown external id is recorded as ignored and changes nothing', function (?string $externalId) {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake([SyncDocumentRequestJob::class]);
    $request = webhookRequest();
    $before = $request->fresh()->toArray();

    postArchivioSmartWebhook($this, webhookBody('request.completed', $externalId, []), 'request.completed', 'evt-ign')->assertStatus(202);

    $event = ArchivioSmartWebhookEvent::query()->sole();
    expect($event->status)->toBe(ArchivioSmartWebhookEventStatus::Ignored)
        ->and($event->document_request_id)->toBeNull()
        ->and($request->fresh()->toArray())->toBe($before);
    Queue::assertNothingPushed();
})->with([
    'null' => [null],
    'unknown' => ['987654'],
    'not numeric' => ['abc'],
]);

it('AC-015: ping and request.created are only recorded', function (string $event) {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    Queue::fake([SyncDocumentRequestJob::class]);
    $request = webhookRequest();
    $before = $request->fresh()->toArray();

    postArchivioSmartWebhook($this, webhookBody($event, $request), $event, 'evt-rec')->assertStatus(202);

    expect(ArchivioSmartWebhookEvent::query()->sole()->status)->toBe(ArchivioSmartWebhookEventStatus::Processed)
        ->and($request->fresh()->toArray())->toBe($before);
    Queue::assertNothingPushed();
})->with(['ping', 'request.created']);

it('AC-016: the webhook secret is encrypted at rest and never returned', function () {
    Sanctum::actingAs(documentChecklistUserWith(['manageConnection']));

    $put = $this->putJson('/api/archivio-smart/settings', ['base_url' => DR_BASE_URL, 'token' => DR_TOKEN, 'webhook_secret' => DR_WEBHOOK_SECRET])
        ->assertOk()
        ->assertJsonPath('data.has_webhook_secret', true)
        ->assertJsonPath('data.webhook_url', url('/api/webhooks/archivio-smart'));
    $get = $this->getJson('/api/archivio-smart/settings')->assertOk()->assertJsonPath('data.has_webhook_secret', true);

    $stored = DB::table('archivio_smart_settings')->value('webhook_secret');
    expect($stored)->not->toBe(DR_WEBHOOK_SECRET)
        ->and($stored)->not->toContain(DR_WEBHOOK_SECRET)
        ->and(ArchivioSmartSetting::current()->webhook_secret)->toBe(DR_WEBHOOK_SECRET)
        ->and($put->getContent())->not->toContain(DR_WEBHOOK_SECRET)
        ->and($get->getContent())->not->toContain(DR_WEBHOOK_SECRET)
        ->and(json_encode(DB::table('activity_log')->get()))->not->toContain(DR_WEBHOOK_SECRET)
        ->and(json_encode(ArchivioSmartSetting::current()->toArray()))->not->toContain('webhook_secret');
});

it('AC-016: the webhook secret is kept when omitted and removed when sent blank', function () {
    Sanctum::actingAs(documentChecklistUserWith(['manageConnection']));
    documentRequestConnection(DR_WEBHOOK_SECRET);

    $this->putJson('/api/archivio-smart/settings', ['base_url' => DR_BASE_URL])
        ->assertOk()->assertJsonPath('data.has_webhook_secret', true);
    expect(ArchivioSmartSetting::current()->webhook_secret)->toBe(DR_WEBHOOK_SECRET);

    $this->putJson('/api/archivio-smart/settings', ['base_url' => DR_BASE_URL, 'webhook_secret' => ''])
        ->assertOk()->assertJsonPath('data.has_webhook_secret', false);
    expect(ArchivioSmartSetting::current()->webhook_secret)->toBeNull();

    $this->putJson('/api/archivio-smart/settings', ['base_url' => DR_BASE_URL, 'webhook_secret' => 'corto'])
        ->assertUnprocessable()->assertJsonValidationErrors(['webhook_secret']);
});

it('AC-016: neither the secret nor the signature nor the body reach the logs', function (bool $valid) {
    documentRequestConnection(DR_WEBHOOK_SECRET);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.json_encode($event->context);
    });
    $request = webhookRequest();
    $body = webhookBody('document.validated', $request, [
        'item' => ['id' => 11],
        'validation' => ['item_status' => 'ko', 'reason_for_customer' => 'Motivo riservato del cliente'],
    ]);
    $signature = $valid ? hash_hmac('sha256', json_encode($body), DR_WEBHOOK_SECRET) : str_repeat('a', 64);

    postArchivioSmartWebhook($this, $body, 'document.validated', 'evt-log', ['Signature' => $signature])->assertStatus($valid ? 202 : 401);

    $dump = json_encode($logged);
    expect($logged)->not->toBeEmpty()
        ->and($dump)->not->toContain(DR_WEBHOOK_SECRET)
        ->and($dump)->not->toContain($signature)
        ->and($dump)->not->toContain('Motivo riservato del cliente');
})->with([true, false]);
