<?php

use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('documentLayoutImageUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function documentLayoutImageUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("document-layouts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-layouts.{$ability}");
        }

        return $user;
    }
}

/**
 * A `config` whose body has a single `image` block referencing $attachmentId
 * (D-11 `image` block, spec 0069 config_schema) — used by AC-095 to prove the
 * `image_in_use` guard.
 *
 * @return array<string, mixed>
 */
function configReferencingImage(int $attachmentId): array
{
    $config = DocumentLayoutFactory::minimalConfig();
    $config['body']['blocks'][] = [
        'id' => 'image-block',
        'type' => 'image',
        'attachment_id' => $attachmentId,
        'width' => 100,
        'height' => null,
        'align' => 'left',
        'wrap' => 'inline',
    ];

    return $config;
}

beforeEach(function () {
    Storage::fake('local');
});

// ---------------------------------------------------------------------------
// POST .../images — upload (AC-090..092)
// ---------------------------------------------------------------------------

it('upload: a valid PNG is persisted under layout_image, attachable is the layout, binary on disk (AC-090)', function () {
    $actor = documentLayoutImageUserWith(['update']);
    $layout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/document-layouts/{$layout->id}/images", [
        'file' => UploadedFile::fake()->image('logo.png', 200, 200),
    ])->assertCreated()->assertJsonPath('success', true);

    $dataUri = $response->json('data.data_uri');
    expect($dataUri)->toStartWith('data:image/png;base64,');

    $attachment = Attachment::firstOrFail();
    expect($attachment->collection)->toBe(DocumentLayout::IMAGE_COLLECTION)
        ->and($attachment->attachable_type)->toBe('document_layout')
        ->and($attachment->attachable_id)->toBe($layout->id);

    Storage::disk('local')->assertExists($attachment->path);
});

it('upload: rejects with 422 (AC-091)', function (callable $payload) {
    $actor = documentLayoutImageUserWith(['update']);
    $layout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/images", $payload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    expect(Attachment::count())->toBe(0);
})->with([
    'file missing' => fn () => [],
    'a PDF' => fn () => ['file' => UploadedFile::fake()->create('doc.pdf', 16, 'application/pdf')],
    'a .webp' => fn () => ['file' => UploadedFile::fake()->image('logo.webp', 100, 100)],
    'a .gif' => fn () => ['file' => UploadedFile::fake()->image('logo.gif', 100, 100)],
    'over max_size' => fn () => ['file' => UploadedFile::fake()->image('big.jpg', 10, 10)->size(((int) config('attachments.max_size')) + 1)],
    // A spoofed upload: filename/extension claim .png, but the real content is
    // reported as `text/plain` — Illuminate\Http\Testing\File's mime detection
    // trusts an explicit ->mimeType() override the same way finfo would trust
    // the real magic bytes of a renamed non-image file in production.
    '.png extension, non-image content' => fn () => ['file' => UploadedFile::fake()->create('fake.png', 10)->mimeType('text/plain')],
]);

it('upload: 422 when the layout already has MAX_IMAGES_PER_LAYOUT images (AC-092)', function () {
    $actor = documentLayoutImageUserWith(['update']);
    $layout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson("/api/document-layouts/{$layout->id}/images", [
            'file' => UploadedFile::fake()->image("logo-{$i}.png", 50, 50),
        ])->assertCreated();
    }

    $this->postJson("/api/document-layouts/{$layout->id}/images", [
        'file' => UploadedFile::fake()->image('logo-6.png', 50, 50),
    ])->assertStatus(422)->assertJsonValidationErrors('file');

    expect(Attachment::count())->toBe(5);
});

it('upload: 403 without document-layouts.update', function () {
    $actor = documentLayoutImageUserWith([]);
    $layout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/images", [
        'file' => UploadedFile::fake()->image('logo.png', 100, 100),
    ])->assertForbidden();

    expect(Attachment::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// GET .../images — list (AC-093)
// ---------------------------------------------------------------------------

it('list: 200 with a data_uri per image (AC-093)', function () {
    $actor = documentLayoutImageUserWith(['view', 'update']);
    $layout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/images", ['file' => UploadedFile::fake()->image('one.png', 50, 50)])->assertCreated();
    $this->postJson("/api/document-layouts/{$layout->id}/images", ['file' => UploadedFile::fake()->image('two.png', 50, 50)])->assertCreated();

    $response = $this->getJson("/api/document-layouts/{$layout->id}/images")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data');

    foreach ($response->json('data') as $image) {
        expect($image['data_uri'])->toStartWith('data:image/png;base64,')
            ->and($image)->toHaveKeys(['attachment_id', 'filename', 'mime_type', 'size', 'data_uri']);
    }
});

it('list: 404 for an inexistent layout', function () {
    $actor = documentLayoutImageUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/document-layouts/999999/images')->assertNotFound();
});

it('list: 403 without document-layouts.view', function () {
    $actor = documentLayoutImageUserWith([]);
    $layout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/document-layouts/{$layout->id}/images")->assertForbidden();
});

// ---------------------------------------------------------------------------
// DELETE .../images/{attachment} (AC-093..095)
// ---------------------------------------------------------------------------

it('delete: 204 and the binary is removed from disk (AC-093)', function () {
    $actor = documentLayoutImageUserWith(['update']);
    $layout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/images", ['file' => UploadedFile::fake()->image('logo.png', 50, 50)])->assertCreated();
    $attachment = Attachment::firstOrFail();
    $path = $attachment->path;

    $this->deleteJson("/api/document-layouts/{$layout->id}/images/{$attachment->id}")->assertNoContent();

    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('delete: 404 when the attachment belongs to a DIFFERENT layout (AC-094)', function () {
    $actor = documentLayoutImageUserWith(['update']);
    $layout = DocumentLayout::factory()->create();
    $otherLayout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$otherLayout->id}/images", ['file' => UploadedFile::fake()->image('logo.png', 50, 50)])->assertCreated();
    $foreignAttachment = Attachment::firstOrFail();

    $this->deleteJson("/api/document-layouts/{$layout->id}/images/{$foreignAttachment->id}")->assertNotFound();

    expect(Attachment::find($foreignAttachment->id))->not->toBeNull();
    Storage::disk('local')->assertExists($foreignAttachment->path);
});

it('delete: 422 image_in_use while a config block references it, 204 once the block is removed (AC-095)', function () {
    $actor = documentLayoutImageUserWith(['update']);
    $layout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$layout->id}/images", ['file' => UploadedFile::fake()->image('logo.png', 50, 50)])->assertCreated();
    $attachment = Attachment::firstOrFail();

    $layout->update(['config' => configReferencingImage($attachment->id)]);

    $this->deleteJson("/api/document-layouts/{$layout->id}/images/{$attachment->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachment_id');

    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk('local')->assertExists($attachment->path);

    $layout->update(['config' => DocumentLayoutFactory::minimalConfig()]);

    $this->deleteJson("/api/document-layouts/{$layout->id}/images/{$attachment->id}")->assertNoContent();

    expect(Attachment::find($attachment->id))->toBeNull();
});

it('delete: 403 without document-layouts.update', function () {
    $actor = documentLayoutImageUserWith(['update']);
    $layout = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);
    $this->postJson("/api/document-layouts/{$layout->id}/images", ['file' => UploadedFile::fake()->image('logo.png', 50, 50)])->assertCreated();
    $attachment = Attachment::firstOrFail();

    $noAbilityActor = documentLayoutImageUserWith([]);
    Sanctum::actingAs($noAbilityActor);

    $this->deleteJson("/api/document-layouts/{$layout->id}/images/{$attachment->id}")->assertForbidden();

    expect(Attachment::find($attachment->id))->not->toBeNull();
});
