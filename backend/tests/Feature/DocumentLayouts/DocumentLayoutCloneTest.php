<?php

use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function documentLayoutCloneUserWith(array $abilities): User
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

/** A `layout_image` of $layout whose binary exists on the faked disk (unless $withFile is false). */
function documentLayoutCloneImage(DocumentLayout $layout, string $name, bool $withFile = true): Attachment
{
    $attachment = Attachment::factory()->create([
        'attachable_type' => 'document_layout',
        'attachable_id' => $layout->id,
        'collection' => DocumentLayout::IMAGE_COLLECTION,
        'path' => "attachments/{$name}",
        'original_name' => $name,
        'mime_type' => 'image/png',
        'extension' => 'png',
    ]);

    if ($withFile) {
        Storage::disk('local')->put($attachment->path, "binary-of-{$name}");
    }

    return $attachment;
}

/**
 * @param  array<int, int>  $attachmentIds  one image block per id, header first then body
 * @return array<string, mixed>
 */
function documentLayoutCloneConfig(array $attachmentIds): array
{
    $config = DocumentLayoutFactory::minimalConfig();

    foreach (array_values($attachmentIds) as $index => $attachmentId) {
        $config[$index === 0 ? 'header' : 'body']['blocks'][] = [
            'id' => "image-{$index}",
            'type' => 'image',
            'attachment_id' => $attachmentId,
            'width' => 100,
            'height' => null,
            'align' => 'left',
            'wrap' => 'inline',
        ];
    }

    return $config;
}

/** @return array<int, int> attachment ids referenced by the config's image blocks */
function documentLayoutCloneImageRefs(array $config): array
{
    return collect(['header', 'body', 'footer'])
        ->flatMap(fn (string $zone) => $config[$zone]['blocks'] ?? [])
        ->where('type', 'image')
        ->pluck('attachment_id')
        ->all();
}

beforeEach(function () {
    Storage::fake('local');
});

it('clones content, settings and images, re-pointing the image blocks to the copies (AC-001)', function () {
    $actor = documentLayoutCloneUserWith(['view', 'create']);
    $source = DocumentLayout::factory()->create(['description' => 'Carta intestata', 'is_active' => false]);
    $logo = documentLayoutCloneImage($source, 'logo.png');
    $letterhead = documentLayoutCloneImage($source, 'letterhead.png');
    $source->update(['config' => documentLayoutCloneConfig([$logo->id, $letterhead->id])]);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/document-layouts/{$source->id}/clone", ['name' => 'Copia', 'code' => 'copia'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Copia')
        ->assertJsonPath('data.code', 'copia')
        ->assertJsonPath('data.description', 'Carta intestata')
        ->assertJsonPath('data.module', $source->module->value)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.is_default', false)
        ->assertJsonCount(2, 'data.images');

    $clone = DocumentLayout::findOrFail($response->json('data.id'));
    $copies = $clone->images()->get();

    expect($copies->pluck('id')->intersect([$logo->id, $letterhead->id]))->toBeEmpty()
        ->and(documentLayoutCloneImageRefs($clone->config))->toEqualCanonicalizing($copies->pluck('id')->all());

    foreach ($copies as $copy) {
        expect(Storage::disk('local')->get($copy->path))->toBe("binary-of-{$copy->original_name}")
            ->and($copy->path)->not->toBeIn([$logo->path, $letterhead->path]);
    }

    // Same tree apart from the image references.
    $stripRefs = fn (array $config) => json_decode(preg_replace('/"attachment_id":\d+/', '"attachment_id":0', json_encode($config)), true);
    expect($stripRefs($clone->config))->toEqual($stripRefs($source->fresh()->config))
        ->and(documentLayoutCloneImageRefs($source->fresh()->config))->toBe([$logo->id, $letterhead->id])
        ->and($source->images()->count())->toBe(2);
});

it('cloning the module default leaves the default where it is (AC-002)', function () {
    $actor = documentLayoutCloneUserWith(['view', 'create']);
    $default = DocumentLayout::factory()->create(['is_default' => true]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$default->id}/clone", ['name' => 'Copia default', 'code' => 'copia_default'])
        ->assertCreated()
        ->assertJsonPath('data.is_default', false);

    expect($default->fresh()->is_default)->toBeTrue()
        ->and(DocumentLayout::query()->where('is_default', true)->count())->toBe(1);
});

it('rejects a name already used in the module and a duplicate or invalid code (AC-003)', function (array $payload, string $field) {
    $actor = documentLayoutCloneUserWith(['view', 'create']);
    $source = DocumentLayout::factory()->create(['name' => 'Esistente', 'code' => 'esistente']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$source->id}/clone", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(DocumentLayout::count())->toBe(1);
})->with([
    'name taken in the module' => [['name' => 'Esistente', 'code' => 'nuovo'], 'name'],
    'code taken' => [['name' => 'Nuovo', 'code' => 'esistente'], 'code'],
    'code not snake_case' => [['name' => 'Nuovo', 'code' => 'Nuovo Codice'], 'code'],
    'name missing' => [['code' => 'nuovo'], 'name'],
]);

it('answers 403 without create or without view, creating nothing (AC-004)', function (array $abilities) {
    $actor = documentLayoutCloneUserWith($abilities);
    $source = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$source->id}/clone", ['name' => 'Copia', 'code' => 'copia'])
        ->assertForbidden();

    expect(DocumentLayout::count())->toBe(1);
})->with([
    'view only' => [['view']],
    'create only' => [['create']],
]);

it('refuses to clone a layout whose image file is gone, leaving no row nor file behind (AC-005)', function () {
    $actor = documentLayoutCloneUserWith(['view', 'create']);
    $source = DocumentLayout::factory()->create();
    $present = documentLayoutCloneImage($source, 'present.png');
    $missing = documentLayoutCloneImage($source, 'missing.png', withFile: false);
    $source->update(['config' => documentLayoutCloneConfig([$present->id, $missing->id])]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/document-layouts/{$source->id}/clone", ['name' => 'Copia', 'code' => 'copia'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images']);

    expect(DocumentLayout::count())->toBe(1)
        ->and(Attachment::count())->toBe(2)
        ->and(Storage::disk('local')->allFiles())->toBe([$present->path]);
});

it('exposes the clone row action only to an actor holding create (AC-006)', function () {
    DocumentLayout::factory()->create(['name' => 'Riga']);

    $rowActions = function (array $abilities): array {
        Sanctum::actingAs(documentLayoutCloneUserWith($abilities));
        $items = $this->postJson('/api/tables/document-layouts/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');

        return collect($items)->firstWhere('name', 'Riga')['actions'];
    };

    expect($rowActions(['viewAny', 'view', 'create']))->toContain('clone')
        ->and($rowActions(['viewAny', 'view']))->not->toContain('clone');
});
