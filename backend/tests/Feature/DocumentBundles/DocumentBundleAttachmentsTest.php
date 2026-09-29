<?php

use App\Models\Attachment;
use App\Models\DocumentBundle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| DocumentBundle as a polymorphic attachment owner (spec 0175, D-7c, AC-004):
| the `document_bundle` alias registered in config/attachments.php, files
| uploaded/listed/deleted/downloaded through the EXISTING generic
| /api/attachments endpoints — no dedicated upload route on this module.
| Same shape as tests/Feature/Contracts/ContractAttachmentTest.php.
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

if (! function_exists('userWithDocumentBundleAttachmentAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithDocumentBundleAttachmentAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("attachments.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("attachments.{$ability}");
        }

        return $user;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

it('AC-004: uploads a file to a document bundle via attachable_type=document_bundle, collection=documents', function () {
    $actor = userWithDocumentBundleAttachmentAbilities(['create', 'view']);
    Sanctum::actingAs($actor);
    $documentBundle = DocumentBundle::factory()->create();

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('contratto.pdf', 16, 'application/pdf'),
        'attachable_type' => 'document_bundle',
        'attachable_id' => $documentBundle->id,
        'collection' => 'documents',
    ])->assertCreated()
        ->assertJsonPath('data.attachable_type', 'document_bundle')
        ->assertJsonPath('data.attachable_id', $documentBundle->id)
        ->assertJsonPath('data.collection', 'documents');

    expect($documentBundle->attachments()->count())->toBe(1);
});

it('AC-004: lists a document bundle\'s files and they are downloadable', function () {
    $actor = userWithDocumentBundleAttachmentAbilities(['create', 'viewAny', 'view']);
    Sanctum::actingAs($actor);
    $documentBundle = DocumentBundle::factory()->create();

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'),
        'attachable_type' => 'document_bundle',
        'attachable_id' => $documentBundle->id,
        'collection' => 'documents',
    ])->assertCreated();

    $response = $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'document_bundle',
        'attachable_id' => $documentBundle->id,
    ]))->assertOk();

    expect($response->json('data'))->toHaveCount(1);

    foreach ($documentBundle->attachments as $attachment) {
        $this->get("/api/attachments/{$attachment->id}/download")->assertOk();
    }
});

it('AC-004: deletes a document bundle\'s file', function () {
    $actor = userWithDocumentBundleAttachmentAbilities(['create', 'delete']);
    Sanctum::actingAs($actor);
    $documentBundle = DocumentBundle::factory()->create();
    $attachment = Attachment::factory()->for($documentBundle, 'attachable')->create(['collection' => 'documents']);
    Storage::disk($attachment->disk)->put($attachment->path, 'fake-pdf-bytes');

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();

    expect(Attachment::find($attachment->id))->toBeNull();
});

it('AC-004: 403 without attachments.create', function () {
    $actor = userWithDocumentBundleAttachmentAbilities([]);
    Sanctum::actingAs($actor);
    $documentBundle = DocumentBundle::factory()->create();

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'),
        'attachable_type' => 'document_bundle',
        'attachable_id' => $documentBundle->id,
        'collection' => 'documents',
    ])->assertForbidden();

    expect(Attachment::count())->toBe(0);
});
