<?php

use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\Attachment;
use App\Models\MigrationRun;
use App\Models\Opportunity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const LEGACY_FILES_BASE = 'https://legacy-files.test';

beforeEach(function () {
    seedMigrationsConfig();
    config(['migrations.legacy_files_base_url' => LEGACY_FILES_BASE]);
    Storage::fake(config('attachments.disk'));
    $this->workOrder = WorkOrder::factory()->create();
    $this->workOrder->forceFill(['old_id' => 19123])->save();
    $this->uploader = User::factory()->create(['old_id' => 79]);
});

/**
 * Contract of GET /api/v2/migration/attachments (spec 0295).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyAttachmentRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 'orderdocuments:123',
        'source' => 'orderdocuments',
        'source_id' => 123,
        'attachable_type' => 'work_order',
        'attachable_id' => 19123,
        'original_name' => 'Contratto firmato.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 20480,
        'url' => LEGACY_FILES_BASE.'/document/123/contratto.pdf',
        'uploaded_by_user_id' => 79,
        'created_at' => '2026-03-02 10:11:12',
        'updated_at' => '2026-03-03 11:12:13',
        'warnings' => [],
    ], $overrides);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyAttachmentsImport(array $records, ?User $actor = null): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/attachments*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'attachments']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-001 imports a link-only document with the contract values, no file and no activity log', function () {
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyAttachmentsImport([legacyAttachmentRecord()], $actor);

    $attachment = Attachment::query()->where('old_source', 'orderdocuments')->where('old_id', 123)->sole();

    expect($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($attachment->attachable_type)->toBe($this->workOrder->getMorphClass())
        ->and($attachment->attachable_id)->toBe($this->workOrder->id)
        ->and($attachment->collection)->toBe('documents')
        ->and($attachment->disk)->toBe(Attachment::LEGACY_DISK)
        ->and($attachment->path)->toBe('orderdocuments/123')
        ->and($attachment->external_url)->toBe(LEGACY_FILES_BASE.'/document/123/contratto.pdf')
        ->and($attachment->original_name)->toBe('Contratto firmato.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->extension)->toBe('pdf')
        ->and($attachment->size)->toBe(20480)
        ->and($attachment->uploaded_by)->toBe($this->uploader->id)
        ->and($attachment->isLegacyLink())->toBeTrue()
        ->and($attachment->created_at->toDateTimeString())->toBe('2026-03-02 10:11:12')
        ->and($attachment->updated_at->toDateTimeString())->toBe('2026-03-03 11:12:13')
        ->and(Storage::disk(config('attachments.disk'))->allFiles())->toBe([])
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});

it('AC-001 imports one document for each attachable alias', function (string $alias, string $modelClass) {
    $parent = $modelClass::factory()->create();
    $parent->forceFill(['old_id' => 555])->save();

    runLegacyAttachmentsImport([legacyAttachmentRecord(['attachable_type' => $alias, 'attachable_id' => 555])]);

    $attachment = Attachment::query()->sole();

    expect($attachment->attachable_type)->toBe($parent->getMorphClass())
        ->and($attachment->attachable_id)->toBe($parent->id);
})->with([
    'opportunity' => ['opportunity', Opportunity::class],
    'task' => ['task', Task::class],
    'registry' => ['registry', Registry::class],
    'purchase request' => ['purchase_request', PurchaseRequest::class],
    'purchase request line' => ['purchase_request_line', PurchaseRequestLine::class],
    'purchase order' => ['purchase_order', PurchaseOrder::class],
]);

it('AC-002 skips a document already imported', function () {
    runLegacyAttachmentsImport([legacyAttachmentRecord()]);

    $run = runLegacyAttachmentsImport([legacyAttachmentRecord()]);

    expect($run->skipped_rows)->toBe(1)
        ->and(Attachment::query()->count())->toBe(1);
});

it('AC-002 keeps two documents with the same source id on different sources', function () {
    $registry = Registry::factory()->create();
    $registry->forceFill(['old_id' => 77])->save();

    $run = runLegacyAttachmentsImport([
        legacyAttachmentRecord(),
        legacyAttachmentRecord([
            'id' => 'documents:123', 'source' => 'documents', 'attachable_type' => 'registry', 'attachable_id' => 77,
            'url' => LEGACY_FILES_BASE.'/documenticliente/123/a.pdf',
        ]),
    ]);

    expect($run->created_rows)->toBe(2)
        ->and(Attachment::query()->where('old_id', 123)->pluck('old_source')->sort()->values()->all())->toBe(['documents', 'orderdocuments']);
});

it('AC-003 fails the row when the parent is not migrated', function () {
    $run = runLegacyAttachmentsImport([legacyAttachmentRecord(['attachable_id' => 99])]);

    expect($run->failed_rows)->toBe(1)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain('Attachable work_order (legacy id 99) not migrated');
});

it('AC-003 keeps the document with a null uploader and a warning when the uploader is not migrated', function () {
    $run = runLegacyAttachmentsImport([legacyAttachmentRecord(['uploaded_by_user_id' => 404])]);

    expect($run->created_rows)->toBe(1)
        ->and(Attachment::query()->sole()->uploaded_by)->toBeNull()
        ->and(legacyTaskWarnings($run))->toHaveCount(1)
        ->and(legacyTaskWarnings($run)[0])->toContain('legacy id 404');
});

it('AC-003 fails an orphan sent by the legacy with its reason', function () {
    $run = runLegacyAttachmentsImport([['id' => 'orderdocuments:9', 'orphan_reason' => 'File assente sullo storage legacy.', 'warnings' => []]]);

    expect($run->failed_rows)->toBe(1)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain('File assente sullo storage legacy');
});

it('AC-004 fails the row on a URL outside the legacy files base or an unknown alias', function (array $overrides, string $reason) {
    $run = runLegacyAttachmentsImport([legacyAttachmentRecord($overrides)]);

    expect($run->failed_rows)->toBe(1)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain($reason);
})->with([
    'other host' => [['url' => 'https://evil.test/document/1/a.pdf'], 'URL is not under the legacy files base URL'],
    'look-alike host' => [['url' => LEGACY_FILES_BASE.'.evil.test/a.pdf'], 'URL is not under the legacy files base URL'],
    'blank url' => [['url' => ''], 'URL is not under the legacy files base URL'],
    'unknown alias' => [['attachable_type' => 'lead'], 'Documents on lead are not supported'],
    'size zero' => [['size' => 0], 'Size must be positive'],
    'blank name' => [['original_name' => ' '], 'Name is required'],
]);

it('AC-005 stores a MIME type outside the allow-list as octet-stream with a warning', function (string $mime) {
    $run = runLegacyAttachmentsImport([legacyAttachmentRecord(['mime_type' => $mime])]);

    expect($run->created_rows)->toBe(1)
        ->and(Attachment::query()->sole()->mime_type)->toBe('application/octet-stream')
        ->and(legacyTaskWarnings($run))->toHaveCount(1)
        ->and(legacyTaskWarnings($run)[0])->toContain('is not allowed');
})->with(['text/html', 'application/pkcs7-mime', 'image/svg+xml']);

it('AC-005 truncates a name over 255 characters with a warning', function () {
    $run = runLegacyAttachmentsImport([legacyAttachmentRecord(['original_name' => str_repeat('a', 300)])]);

    expect(mb_strlen(Attachment::query()->sole()->original_name))->toBe(255)
        ->and(legacyTaskWarnings($run)[0])->toContain('Name truncated to 255');
});

it('AC-012 orders the source in phase 18 with the contract dependencies', function () {
    $source = app(MigrationRegistry::class)->resolve('attachments');
    $dependencies = collect($source->dependencies())->mapWithKeys(fn ($d) => [$d->source => $d->required]);

    expect(MigrationOrder::phaseOf('attachments'))->toBe(18)
        ->and(MigrationOrder::phaseOf('attachments'))->toBe(MigrationOrder::phaseOf('notes'))
        ->and($source->label())->toBe('Documents')
        ->and($dependencies->all())->toBe([
            'work-orders' => true,
            'users' => false,
            'opportunities' => false,
            'tasks' => false,
            'registries' => false,
            'purchase-requests' => false,
            'purchase-orders' => false,
        ]);
});

it('AC-012 is unavailable without the legacy files base URL', function (?string $baseUrl, bool $available) {
    config(['migrations.legacy_files_base_url' => $baseUrl]);

    expect(app(MigrationRegistry::class)->resolve('attachments')->isAvailable())->toBe($available);
})->with([
    'empty' => [null, false],
    'blank' => ['', false],
    'set' => [LEGACY_FILES_BASE, true],
]);
