<?php

use App\Enums\DocumentChecklistSyncStatus;
use App\Jobs\SyncDocumentChecklistJob;
use App\Models\ArchivioSmartSetting;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use App\Models\DocumentChecklistSyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const AS_BASE_URL = 'https://archivio.test/api/v1';
const AS_TOKEN = 'as_secret_token_987654321';

function archivioSetting(): ArchivioSmartSetting
{
    return ArchivioSmartSetting::factory()->create(['base_url' => AS_BASE_URL.'/', 'token' => AS_TOKEN]);
}

function runSyncJob(DocumentChecklist $checklist, int $attempt = 1): SyncDocumentChecklistJob
{
    $job = (new SyncDocumentChecklistJob($checklist->id))->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    app()->call([$job, 'handle']);

    return $job;
}

it('sends the PUT with bearer token and the contract body (AC-008)', function () {
    archivioSetting();
    Http::fake(['*' => Http::response(['data' => []], 200)]);
    $checklist = DocumentChecklist::factory()->create(['code' => 'APERTURA', 'name' => 'Apertura', 'description' => null]);
    $item = DocumentChecklistItem::factory()->for($checklist, 'checklist')->create(['position' => 0, 'max_age_days' => 30]);

    runSyncJob($checklist);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($checklist, $item) {
        $body = $request->data();

        return $request->method() === 'PUT'
            && $request->url() === AS_BASE_URL.'/checklists/'.$checklist->id
            && $request->hasHeader('Authorization', 'Bearer '.AS_TOKEN)
            && $request->hasHeader('Accept', 'application/json')
            && $body['code'] === 'APERTURA'
            && $body['description'] === null
            && $body['is_active'] === true
            && $body['items'][0]['external_id'] === (string) $item->id
            && $body['items'][0]['validation_rules'] === ['max_age_days' => 30, 'date_field' => 'issue_date'];
    });
});

it('marks the checklist synced and logs success on 201 and 200 (AC-009)', function (int $status) {
    archivioSetting();
    Http::fake(['*' => Http::response(['data' => []], $status)]);
    $checklist = DocumentChecklist::factory()->create(['sync_error' => 'old']);

    runSyncJob($checklist);

    $checklist->refresh();
    $log = $checklist->syncLogs()->sole();
    expect($checklist->sync_status)->toBe(DocumentChecklistSyncStatus::Synced)
        ->and($checklist->sync_error)->toBeNull()
        ->and($checklist->synced_at)->not->toBeNull()
        ->and($checklist->first_synced_at)->not->toBeNull()
        ->and($log->outcome->value)->toBe('success')
        ->and($log->operation->value)->toBe('upsert')
        ->and($log->http_status)->toBe($status)
        ->and($log->attempt)->toBe(1);
})->with([200, 201]);

it('does not write activity log rows for sync state changes', function () {
    archivioSetting();
    Http::fake(['*' => Http::response([], 200)]);
    $checklist = DocumentChecklist::factory()->create();
    $before = DB::table('activity_log')->count();

    runSyncJob($checklist);

    expect(DB::table('activity_log')->count())->toBe($before);
});

it('fails without retry on 422 and stores the server messages (AC-010)', function () {
    archivioSetting();
    Http::fake(['*' => Http::response(['errors' => ['code' => ["Il codice e' gia' in uso."], 'name' => ['Nome non valido.']]], 422)]);
    $checklist = DocumentChecklist::factory()->create();

    $job = runSyncJob($checklist);

    $checklist->refresh();
    $log = $checklist->syncLogs()->sole();
    $job->assertNotReleased();
    expect($checklist->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($checklist->sync_error)->toContain("Il codice e' gia' in uso.")->toContain('Nome non valido.')
        ->and($checklist->first_synced_at)->toBeNull()
        ->and($log->outcome->value)->toBe('failed')
        ->and($log->http_status)->toBe(422);
});

it('truncates a long 422 message to the configured length', function () {
    archivioSetting();
    Http::fake(['*' => Http::response(['errors' => ['name' => [str_repeat('x', 3000)]]], 422)]);
    $checklist = DocumentChecklist::factory()->create();

    runSyncJob($checklist);

    expect(mb_strlen($checklist->fresh()->sync_error))->toBe(config('archivio-smart.error_message_max_length'));
});

it('fails without retry on 401, 409 and other 4xx with their i18n message (AC-010)', function (int $status, string $messageKey, array $replace) {
    archivioSetting();
    Http::fake(['*' => Http::response([], $status)]);
    $checklist = DocumentChecklist::factory()->create();

    $job = runSyncJob($checklist);

    $job->assertNotReleased();
    expect($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($checklist->fresh()->sync_error)->toBe(__($messageKey, $replace))
        ->and($checklist->syncLogs()->sole()->http_status)->toBe($status);
})->with([
    [401, 'archivio_smart.unauthorized', []],
    [409, 'archivio_smart.conflict', []],
    [404, 'archivio_smart.unexpected_status', ['status' => 404]],
]);

it('releases with the Retry-After seconds on 429 (AC-011)', function () {
    archivioSetting();
    Http::fake(['*' => Http::response([], 429, ['Retry-After' => '7'])]);
    $checklist = DocumentChecklist::factory()->create();

    $job = runSyncJob($checklist);

    $job->assertReleased(7);
    expect($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Pending)
        ->and($checklist->syncLogs()->sole()->outcome->value)->toBe('retrying')
        ->and($checklist->syncLogs()->sole()->http_status)->toBe(429);
});

it('releases with the fallback delay on 429 without Retry-After', function () {
    archivioSetting();
    Http::fake(['*' => Http::response([], 429)]);

    runSyncJob(DocumentChecklist::factory()->create())->assertReleased(60);
});

it('releases with the backoff of the current attempt on 5xx (AC-011)', function (int $attempt, int $delay) {
    archivioSetting();
    Http::fake(['*' => Http::response([], 503)]);
    $checklist = DocumentChecklist::factory()->create();

    runSyncJob($checklist, $attempt)->assertReleased($delay);

    expect($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Pending)
        ->and($checklist->syncLogs()->sole()->outcome->value)->toBe('retrying')
        ->and($checklist->syncLogs()->sole()->attempt)->toBe($attempt);
})->with([[1, 10], [2, 30], [3, 90], [4, 300]]);

it('retries on a connection exception without exposing its message', function () {
    archivioSetting();
    Http::fake(fn () => throw new ConnectionException('cURL error: '.AS_TOKEN));
    $checklist = DocumentChecklist::factory()->create();

    runSyncJob($checklist, 2)->assertReleased(30);

    $log = $checklist->syncLogs()->sole();
    expect($log->outcome->value)->toBe('retrying')
        ->and($log->http_status)->toBeNull()
        ->and($log->message)->not->toContain(AS_TOKEN);
});

it('fails at the last attempt instead of releasing (AC-011)', function (string $kind) {
    archivioSetting();
    $kind === 'http'
        ? Http::fake(['*' => Http::response([], 503)])
        : Http::fake(fn () => throw new ConnectionException('down'));
    $checklist = DocumentChecklist::factory()->create();

    $job = runSyncJob($checklist, config('archivio-smart.tries'));

    $job->assertNotReleased();
    expect($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($checklist->fresh()->sync_error)->not->toBeEmpty()
        ->and($checklist->syncLogs()->sole()->outcome->value)->toBe('failed');
})->with(['http', 'network']);

it('reads the current state when it runs (AC-012)', function () {
    archivioSetting();
    Http::fake(['*' => Http::response([], 200)]);
    $checklist = DocumentChecklist::factory()->create(['name' => 'Old name']);
    $job = (new SyncDocumentChecklistJob($checklist->id))->withFakeQueueInteractions();

    $checklist->update(['name' => 'New name', 'is_active' => false]);
    app()->call([$job, 'handle']);

    Http::assertSent(fn (Request $request) => $request['name'] === 'New name' && $request['is_active'] === false);
});

it('prevents overlapping runs with a key equal to the checklist id (AC-012)', function () {
    $job = new SyncDocumentChecklistJob(42);
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->getLockKey($job))->toEndWith(':42')
        ->and($job->tries)->toBe(config('archivio-smart.tries'));
});

it('sends DELETE for a soft deleted checklist and marks it synced (AC-013)', function () {
    archivioSetting();
    Http::fake(['*' => Http::response(null, 204)]);
    $checklist = DocumentChecklist::factory()->create();
    $checklist->delete();

    runSyncJob($checklist);

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === AS_BASE_URL.'/checklists/'.$checklist->id);
    $fresh = DocumentChecklist::withTrashed()->find($checklist->id);
    expect($fresh->sync_status)->toBe(DocumentChecklistSyncStatus::Synced)
        ->and($fresh->first_synced_at)->toBeNull()
        ->and($checklist->syncLogs()->sole()->operation->value)->toBe('delete');
});

it('makes no HTTP call when the connection is not configured (AC-016)', function (?array $setting) {
    Http::fake();
    if ($setting !== null) {
        ArchivioSmartSetting::factory()->create($setting);
    }
    $checklist = DocumentChecklist::factory()->create();

    $job = runSyncJob($checklist);

    Http::assertNothingSent();
    $job->assertNotReleased();
    $log = $checklist->syncLogs()->sole();
    expect($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($checklist->fresh()->sync_error)->toBe(__('archivio_smart.not_configured'))
        ->and($log->http_status)->toBeNull()
        ->and($log->outcome->value)->toBe('failed');
})->with([
    'no row' => [null],
    'no token' => [['base_url' => AS_BASE_URL, 'token' => null]],
]);

it('does nothing when the checklist no longer exists', function () {
    archivioSetting();
    Http::fake();

    app()->call([new SyncDocumentChecklistJob(999999), 'handle']);

    Http::assertNothingSent();
    expect(DocumentChecklistSyncLog::query()->count())->toBe(0);
});

it('never leaks the token in logs, sync_error or activity log (AC-017)', function (int $status) {
    archivioSetting();
    Http::fake(['*' => Http::response(['errors' => ['code' => ['Errore']]], $status)]);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.json_encode($event->context);
    });
    $checklist = DocumentChecklist::factory()->create();

    runSyncJob($checklist, 1);
    (new SyncDocumentChecklistJob($checklist->id))->failed(new RuntimeException('boom'));

    $dump = json_encode([
        DB::table('document_checklist_sync_logs')->get(),
        DB::table('document_checklists')->get(),
        DB::table('activity_log')->get(),
        $logged,
    ]);
    expect($dump)->not->toContain(AS_TOKEN);
})->with([422, 401, 503]);

it('marks the checklist failed with a generic message when the job is killed', function () {
    $checklist = DocumentChecklist::factory()->create();

    (new SyncDocumentChecklistJob($checklist->id))->failed(new RuntimeException('boom'));

    expect($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($checklist->fresh()->sync_error)->toBe(__('archivio_smart.job_generic_failure'));
});
