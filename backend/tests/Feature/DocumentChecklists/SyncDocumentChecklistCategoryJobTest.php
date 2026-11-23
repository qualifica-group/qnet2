<?php

use App\Enums\DocumentChecklistSyncStatus;
use App\Jobs\SyncDocumentChecklistCategoryJob;
use App\Jobs\SyncDocumentChecklistJob;
use App\Models\ArchivioSmartSetting;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/*
| Category sync job and the category-first rule of the checklist job (spec 0410):
| AC-004, AC-005, AC-007, AC-008, AC-009, AC-010, AC-015.
*/

uses(RefreshDatabase::class);

const CATEGORY_AS_BASE_URL = 'https://archivio.test/api/v1';
const CATEGORY_AS_TOKEN = 'as_secret_token_category_555';

function categoryArchivioSetting(): ArchivioSmartSetting
{
    return ArchivioSmartSetting::factory()->create(['base_url' => CATEGORY_AS_BASE_URL.'/', 'token' => CATEGORY_AS_TOKEN]);
}

function runCategoryJob(DocumentChecklistCategory $category, int $attempt = 1): SyncDocumentChecklistCategoryJob
{
    $job = (new SyncDocumentChecklistCategoryJob($category->id))->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    app()->call([$job, 'handle']);

    return $job;
}

function runChecklistJobForCategory(DocumentChecklist $checklist, int $attempt = 1): SyncDocumentChecklistJob
{
    $job = (new SyncDocumentChecklistJob($checklist->id))->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    app()->call([$job, 'handle']);

    return $job;
}

it('AC-004: sends the PUT with bearer token and the full body, description null when blank', function () {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response([], 201)]);
    $category = DocumentChecklistCategory::factory()->create(['name' => 'Mutui', 'description' => '', 'sort_order' => 4]);

    runCategoryJob($category);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === CATEGORY_AS_BASE_URL.'/categories/'.$category->id
        && $request->hasHeader('Authorization', 'Bearer '.CATEGORY_AS_TOKEN)
        && $request->data() === ['name' => 'Mutui', 'description' => null, 'sort_order' => 4]);
});

it('AC-004: 201 and 200 mark the category synced and log success; repeating it is harmless', function (int $status) {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response([], $status)]);
    $category = DocumentChecklistCategory::factory()->create(['sync_error' => 'old']);

    runCategoryJob($category);
    runCategoryJob($category);

    $category->refresh();
    expect($category->sync_status)->toBe(DocumentChecklistSyncStatus::Synced)
        ->and($category->sync_error)->toBeNull()
        ->and($category->synced_at)->not->toBeNull()
        ->and($category->syncLogs()->count())->toBe(2)
        ->and($category->syncLogs()->first()->outcome->value)->toBe('success')
        ->and($category->syncLogs()->first()->operation->value)->toBe('upsert');
})->with([200, 201]);

it('AC-005: 422 fails with the server message and no release', function () {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response(['errors' => ['name' => ["Il valore di nome e' gia' in uso."]]], 422)]);
    $category = DocumentChecklistCategory::factory()->create();

    $job = runCategoryJob($category);

    $job->assertNotReleased();
    expect($category->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($category->fresh()->sync_error)->toBe("Il valore di nome e' gia' in uso.")
        ->and($category->syncLogs()->sole()->http_status)->toBe(422);
});

it('AC-005: 409 fails with the category i18n message', function () {
    app()->setLocale('it');
    categoryArchivioSetting();
    Http::fake(['*' => Http::response([], 409)]);
    $category = DocumentChecklistCategory::factory()->create();

    runCategoryJob($category)->assertNotReleased();

    expect($category->fresh()->sync_error)->toBe(__('archivio_smart.category_conflict'))
        ->and($category->fresh()->sync_error)->toContain('una categoria non creata da QNet 2');
});

it('AC-005: 429 releases with Retry-After and 503 with the backoff of the attempt', function () {
    categoryArchivioSetting();
    $category = DocumentChecklistCategory::factory()->create();

    Http::fake(['*' => Http::sequence()->push([], 429, ['Retry-After' => '7'])->push([], 503)]);

    runCategoryJob($category)->assertReleased(7);
    runCategoryJob($category, 2)->assertReleased(30);

    expect($category->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Pending)
        ->and($category->syncLogs()->pluck('outcome')->map->value->all())->toBe(['retrying', 'retrying']);
});

it('AC-005: the last attempt fails instead of releasing', function (string $kind) {
    categoryArchivioSetting();
    $kind === 'http'
        ? Http::fake(['*' => Http::response([], 503)])
        : Http::fake(fn () => throw new ConnectionException('down '.CATEGORY_AS_TOKEN));
    $category = DocumentChecklistCategory::factory()->create();

    $job = runCategoryJob($category, config('archivio-smart.tries'));

    $job->assertNotReleased();
    expect($category->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($category->fresh()->sync_error)->not->toContain(CATEGORY_AS_TOKEN)
        ->and($category->syncLogs()->sole()->outcome->value)->toBe('failed');
})->with(['http', 'network']);

it('AC-005: without a connection nothing is sent and the category fails', function () {
    Http::fake();
    $category = DocumentChecklistCategory::factory()->create();

    runCategoryJob($category)->assertNotReleased();

    Http::assertNothingSent();
    expect($category->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($category->fresh()->sync_error)->toBe(__('archivio_smart.not_configured'))
        ->and($category->syncLogs()->sole()->http_status)->toBeNull();
});

it('AC-006: a soft-deleted category sends DELETE and 204 marks it synced', function () {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response('', 204)]);
    $category = DocumentChecklistCategory::factory()->create();
    $category->delete();

    runCategoryJob($category);

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === CATEGORY_AS_BASE_URL.'/categories/'.$category->id);
    $fresh = DocumentChecklistCategory::withTrashed()->find($category->id);
    expect($fresh->sync_status)->toBe(DocumentChecklistSyncStatus::Synced)
        ->and($fresh->syncLogs()->sole()->operation->value)->toBe('delete');
});

it('AC-007: a 422 on DELETE is retried while a checklist is pending, final otherwise', function () {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response(['errors' => ['category' => ['Contiene ancora checklist.']]], 422)]);
    $category = DocumentChecklistCategory::factory()->create();
    $category->delete();
    DocumentChecklist::factory()->create(['sync_status' => 'pending']);

    runCategoryJob($category)->assertReleased(10);

    $fresh = DocumentChecklistCategory::withTrashed()->find($category->id);
    expect($fresh->sync_status)->toBe(DocumentChecklistSyncStatus::Pending)
        ->and($fresh->syncLogs()->sole()->outcome->value)->toBe('retrying');

    DocumentChecklist::query()->update(['sync_status' => 'synced']);

    $job = runCategoryJob($category, 2);

    $job->assertNotReleased();
    $fresh->refresh();
    expect($fresh->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($fresh->sync_error)->toBe('Contiene ancora checklist.');
});

it('AC-007: the pending-checklist retry still ends in failed at the last attempt', function () {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response(['errors' => ['category' => ['Contiene ancora checklist.']]], 422)]);
    $category = DocumentChecklistCategory::factory()->create();
    $category->delete();
    DocumentChecklist::factory()->create(['sync_status' => 'pending']);

    runCategoryJob($category, config('archivio-smart.tries'))->assertNotReleased();

    expect(DocumentChecklistCategory::withTrashed()->find($category->id)->sync_status)->toBe(DocumentChecklistSyncStatus::Failed);
});

it('AC-008: the checklist PUT always carries category_external_id', function (bool $withCategory) {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response([], 200)]);
    $category = DocumentChecklistCategory::factory()->create(['sync_status' => 'synced']);
    $checklist = DocumentChecklist::factory()->create(['category_id' => $withCategory ? $category->id : null]);

    runChecklistJobForCategory($checklist);

    Http::assertSent(function (Request $request) use ($withCategory, $category) {
        $body = $request->data();

        return str_contains($request->url(), '/checklists/')
            && array_key_exists('category_external_id', $body)
            && $body['category_external_id'] === ($withCategory ? (string) $category->id : null);
    });
})->with([true, false]);

it('AC-009: a pending category is sent before the checklist in the same attempt', function () {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response([], 200)]);
    $category = DocumentChecklistCategory::factory()->create(['sync_status' => 'pending']);
    $checklist = DocumentChecklist::factory()->create(['category_id' => $category->id]);

    runChecklistJobForCategory($checklist);

    $urls = collect(Http::recorded())->map(fn (array $pair) => $pair[0]->method().' '.$pair[0]->url())->all();
    expect($urls)->toBe([
        'PUT '.CATEGORY_AS_BASE_URL.'/categories/'.$category->id,
        'PUT '.CATEGORY_AS_BASE_URL.'/checklists/'.$checklist->id,
    ])
        ->and($category->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Synced)
        ->and($category->syncLogs()->sole()->outcome->value)->toBe('success')
        ->and($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Synced)
        ->and($checklist->syncLogs()->count())->toBe(1);
});

it('AC-009: an already synced category is not sent again', function () {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response([], 200)]);
    $category = DocumentChecklistCategory::factory()->create(['sync_status' => 'synced']);
    $checklist = DocumentChecklist::factory()->create(['category_id' => $category->id]);

    runChecklistJobForCategory($checklist);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/checklists/'));
});

it('AC-010: a category rejected with 422 fails the checklist without sending it', function () {
    app()->setLocale('it');
    categoryArchivioSetting();
    Http::fake(['*' => Http::response(['errors' => ['name' => ['Nome gia usato.']]], 422)]);
    $category = DocumentChecklistCategory::factory()->create(['sync_status' => 'pending']);
    $checklist = DocumentChecklist::factory()->create(['category_id' => $category->id]);

    $job = runChecklistJobForCategory($checklist);

    $job->assertNotReleased();
    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/checklists/'));
    expect($category->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Failed)
        ->and($checklist->fresh()->sync_error)->toStartWith(__('archivio_smart.category_not_synced', ['reason' => '']))
        ->and($checklist->fresh()->sync_error)->toContain('Nome gia usato.')
        ->and($checklist->fresh()->sync_error)->toStartWith('Categoria non sincronizzata');
});

it('AC-010: a category failing with 503 sends the checklist back to retry', function () {
    categoryArchivioSetting();
    Http::fake(['*' => Http::response([], 503)]);
    $category = DocumentChecklistCategory::factory()->create(['sync_status' => 'pending']);
    $checklist = DocumentChecklist::factory()->create(['category_id' => $category->id]);

    $job = runChecklistJobForCategory($checklist);

    $job->assertReleased(10);
    Http::assertSentCount(1);
    expect($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Pending)
        ->and($category->syncLogs()->sole()->outcome->value)->toBe('retrying');
});

it('AC-015: the token never reaches sync logs, sync_error, the activity log or Log', function () {
    categoryArchivioSetting();
    Http::fake(fn () => throw new ConnectionException('cURL error: '.CATEGORY_AS_TOKEN));
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.json_encode($event->context);
    });
    $category = DocumentChecklistCategory::factory()->create();

    runCategoryJob($category, config('archivio-smart.tries'));
    (new SyncDocumentChecklistCategoryJob($category->id))->failed(new RuntimeException(CATEGORY_AS_TOKEN));

    $dump = json_encode([
        DB::table('document_checklist_category_sync_logs')->get(),
        DB::table('document_checklist_categories')->get(),
        DB::table('activity_log')->get(),
        $logged,
    ]);
    expect($dump)->not->toContain(CATEGORY_AS_TOKEN);
});

it('serialises runs for the same category and exposes the configured tries', function () {
    $job = new SyncDocumentChecklistCategoryJob(5);

    expect($job->tries)->toBe(config('archivio-smart.tries'))
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($job->middleware()[0]->key)->toContain('category-5');
});

it('does nothing for a category that no longer exists', function () {
    Http::fake();

    app()->call([new SyncDocumentChecklistCategoryJob(999999), 'handle']);

    Http::assertNothingSent();
});
