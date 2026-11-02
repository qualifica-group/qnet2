<?php

use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\LegacyFileUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0295 (AC-006..AC-011): link-only legacy documents are proxied from the
 * legacy QNet, never read from a local disk, and never written to one.
 */
uses(RefreshDatabase::class);

const LEGACY_BASE = 'https://legacy.test';

beforeEach(function () {
    Storage::fake('local');
    config(['migrations.legacy_files_base_url' => LEGACY_BASE]);
});

/**
 * A user holding the `attachments.*` permissions plus view on the work orders
 * they supervise, and a legacy document on a work order they can see.
 *
 * @return array{0: User, 1: WorkOrder, 2: Attachment}
 */
function legacyDocumentFixture(array $attributes = []): array
{
    foreach (['viewAny', 'view', 'create', 'delete'] as $ability) {
        Permission::findOrCreate("attachments.{$ability}");
    }

    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['view', 'sendEmail', 'viewDocuments']);
    $actor->givePermissionTo(['attachments.viewAny', 'attachments.view', 'attachments.delete']);

    $attachment = Attachment::factory()->legacyLink()->create(array_merge([
        'attachable_type' => 'work_order',
        'attachable_id' => $workOrder->id,
        'original_name' => 'Contratto firmato.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 11,
    ], $attributes));

    return [$actor, $workOrder, $attachment];
}

// ---------------------------------------------------------------------------
// AC-006 download / view
// ---------------------------------------------------------------------------

it('AC-006: download streams the legacy bytes with the stored mime type and nosniff', function () {
    [$actor, , $attachment] = legacyDocumentFixture();
    Http::fake([$attachment->external_url => Http::response('legacy-body', 200, ['Content-Type' => 'text/html'])]);
    Sanctum::actingAs($actor);

    $response = $this->get("/api/attachments/{$attachment->id}/download")->assertOk();

    expect($response->streamedContent())->toBe('legacy-body')
        ->and($response->headers->get('Content-Type'))->toStartWith('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('Content-Disposition'))->toContain('Contratto firmato.pdf')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    Http::assertSent(fn (Request $request) => $request->url() === $attachment->external_url);
});

it('AC-006: view serves the same bytes inline', function () {
    [$actor, , $attachment] = legacyDocumentFixture();
    Http::fake([$attachment->external_url => Http::response('legacy-body', 200, ['Content-Type' => 'text/html'])]);
    Sanctum::actingAs($actor);

    $response = $this->get("/api/attachments/{$attachment->id}/view")->assertOk();

    expect($response->streamedContent())->toBe('legacy-body')
        ->and($response->headers->get('Content-Type'))->toStartWith('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

// ---------------------------------------------------------------------------
// AC-007 authorization comes first
// ---------------------------------------------------------------------------

it('AC-007: a user without attachments.view is 403 and the legacy is never called', function () {
    [, , $attachment] = legacyDocumentFixture();
    Http::fake();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/attachments/{$attachment->id}/download")->assertForbidden();
    $this->getJson("/api/attachments/{$attachment->id}/view")->assertForbidden();

    Http::assertNothingSent();
});

it('AC-007: an owner-scoped legacy document is 403 without view on its work order', function () {
    [$actor, , $attachment] = legacyDocumentFixture();
    $hidden = WorkOrder::factory()->create();
    $attachment->forceFill(['attachable_id' => $hidden->id])->save();
    Http::fake();
    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments/{$attachment->id}/download")->assertForbidden();
    $this->getJson("/api/attachments/{$attachment->id}/view")->assertForbidden();

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-008 error mapping
// ---------------------------------------------------------------------------

it('AC-008: a legacy 404 is a 404 envelope without the legacy url', function () {
    [$actor, , $attachment] = legacyDocumentFixture();
    Http::fake([$attachment->external_url => Http::response('', 404)]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/attachments/{$attachment->id}/download")->assertNotFound();

    expect($response->json('success'))->toBeFalse()
        ->and($response->getContent())->not->toContain('legacy.test');
});

it('AC-008: a legacy 5xx, an unreachable legacy or a redirect is a 502 envelope', function (Closure $fake) {
    [$actor, , $attachment] = legacyDocumentFixture();
    Http::fake([$attachment->external_url => $fake()]);
    Sanctum::actingAs($actor);

    foreach (['download', 'view'] as $endpoint) {
        $response = $this->getJson("/api/attachments/{$attachment->id}/{$endpoint}")->assertStatus(502);

        expect($response->json('success'))->toBeFalse()
            ->and($response->getContent())->not->toContain('legacy.test');
    }
})->with([
    'server error' => [fn () => fn () => Http::response('boom', 500)],
    'unreachable' => [fn () => fn () => throw new ConnectionException('cURL error 28: timed out')],
    'redirect' => [fn () => fn () => Http::response('', 302, ['Location' => 'https://evil.test/x'])],
]);

it('AC-008: an external_url outside the allowed base is a 404 and nothing is requested', function () {
    [$actor, , $attachment] = legacyDocumentFixture();
    $attachment->forceFill(['external_url' => 'https://legacy.test.evil.com/document/1/file.pdf'])->save();
    Http::fake();
    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments/{$attachment->id}/download")->assertNotFound();
    $this->getJson("/api/attachments/{$attachment->id}/view")->assertNotFound();

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-009 delete
// ---------------------------------------------------------------------------

it('AC-009: deleting a legacy document removes only the row', function () {
    [$actor, , $attachment] = legacyDocumentFixture();
    Http::fake();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();

    expect(Attachment::query()->find($attachment->id))->toBeNull();
    Http::assertNothingSent();
});

it('AC-009: force deleting the owner removes its legacy documents without touching any disk', function () {
    $registry = Registry::factory()->create();
    $attachment = Attachment::factory()->legacyLink()->create([
        'attachable_type' => 'registry',
        'attachable_id' => $registry->id,
    ]);
    Http::fake();

    // Any Storage call on the pseudo-disk would throw: no disk is named 'legacy'.
    $registry->forceDelete();

    expect(Attachment::query()->find($attachment->id))->toBeNull();
    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-010 import into an outbound email
// ---------------------------------------------------------------------------

function importLegacyIntoEmail(object $test, WorkOrder $workOrder, User $actor, Attachment $source): TestResponse
{
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create(['sender_user_id' => $actor->id]);

    return $test->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/import", [
        'source' => 'documents',
        'attachment_ids' => [$source->id],
    ]);
}

it('AC-010: importing a legacy work order document stores a local copy and leaves the source untouched', function () {
    [$actor, $workOrder, $source] = legacyDocumentFixture();
    Http::fake([$source->external_url => Http::response('legacy-body', 200)]);
    Sanctum::actingAs($actor);

    importLegacyIntoEmail($this, $workOrder, $actor, $source)->assertCreated();

    $copy = Attachment::query()->where('collection', OutboundEmail::ATTACHMENT_COLLECTION)->sole();
    expect($copy->isLegacyLink())->toBeFalse()
        ->and($copy->external_url)->toBeNull()
        ->and($copy->old_source)->toBeNull()
        ->and($copy->old_id)->toBeNull()
        ->and($copy->disk)->toBe(config('attachments.disk'))
        ->and($copy->original_name)->toBe('Contratto firmato.pdf')
        ->and($copy->size)->toBe(11);
    expect(Storage::disk($copy->disk)->get($copy->path))->toBe('legacy-body');

    $source->refresh();
    expect($source->isLegacyLink())->toBeTrue()
        ->and($source->disk)->toBe(Attachment::LEGACY_DISK);
});

it('AC-010: importing a legacy document of the work order registry stores a local copy', function () {
    [$actor, $workOrder, $source] = legacyDocumentFixture();
    $registry = $workOrder->quote->opportunity->registry;
    Permission::findOrCreate('registries.viewDocuments');
    $actor->givePermissionTo('registries.viewDocuments');
    $source->forceFill(['attachable_type' => 'registry', 'attachable_id' => $registry->id])->save();
    Http::fake([$source->external_url => Http::response('registry-body', 200)]);
    Sanctum::actingAs($actor);

    importLegacyIntoEmail($this, $workOrder, $actor, $source)->assertCreated();

    $copy = Attachment::query()->where('collection', OutboundEmail::ATTACHMENT_COLLECTION)->sole();
    expect(Storage::disk($copy->disk)->get($copy->path))->toBe('registry-body')
        ->and($copy->external_url)->toBeNull();
});

it('AC-010: an unreachable legacy is a 502 and leaves no row or file on the email', function () {
    [$actor, $workOrder, $source] = legacyDocumentFixture();
    Http::fake([$source->external_url => Http::response('', 500)]);
    Sanctum::actingAs($actor);

    importLegacyIntoEmail($this, $workOrder, $actor, $source)->assertStatus(502);

    expect(Attachment::query()->where('collection', OutboundEmail::ATTACHMENT_COLLECTION)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('AC-010: a legacy file over attachments.max_size is a 422 with nothing written', function () {
    config(['attachments.max_size' => 1]);
    [$actor, $workOrder, $source] = legacyDocumentFixture();
    Http::fake([$source->external_url => Http::response(str_repeat('a', 2048), 200)]);
    Sanctum::actingAs($actor);

    importLegacyIntoEmail($this, $workOrder, $actor, $source)->assertUnprocessable();

    expect(Attachment::query()->where('collection', OutboundEmail::ATTACHMENT_COLLECTION)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

// ---------------------------------------------------------------------------
// LegacyFileUrl (D-11)
// ---------------------------------------------------------------------------

it('LegacyFileUrl accepts only http(s) urls under the configured base', function (?string $url, bool $allowed) {
    expect(LegacyFileUrl::isAllowed($url))->toBe($allowed);
})->with([
    'under base' => ['https://legacy.test/document/1/a.pdf', true],
    'lookalike host' => ['https://legacy.test.evil.com/document/1/a.pdf', false],
    'other host' => ['https://evil.test/document/1/a.pdf', false],
    'ftp scheme' => ['ftp://legacy.test/a.pdf', false],
    'file scheme' => ['file:///etc/passwd', false],
    'null' => [null, false],
]);

it('LegacyFileUrl rejects everything when the base is empty', function () {
    config(['migrations.legacy_files_base_url' => '']);

    expect(LegacyFileUrl::isAllowed('https://legacy.test/document/1/a.pdf'))->toBeFalse();
});
