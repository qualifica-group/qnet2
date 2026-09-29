<?php

use App\Models\DocumentBundle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| `document-bundles` CRUD (spec 0175, D-7c/D-14, AC-004)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('store: 201 with the submitted fields, files_count 0 (no file yet)', function () {
    Sanctum::actingAs(documentBundleUserWith(['create', 'view']));

    $this->postJson('/api/document-bundles', ['name' => 'Contratto standard', 'description' => 'Descrizione'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Contratto standard')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.files_count', 0);

    $this->assertDatabaseHas('document_bundles', ['name' => 'Contratto standard']);
});

it('store: 403 without document-bundles.create', function () {
    Sanctum::actingAs(documentBundleUserWith([]));

    $this->postJson('/api/document-bundles', ['name' => 'Contratto standard'])->assertForbidden();
});

it('store: 422 on a duplicate name', function () {
    Sanctum::actingAs(documentBundleUserWith(['create']));
    DocumentBundle::factory()->create(['name' => 'Gia esistente']);

    $this->postJson('/api/document-bundles', ['name' => 'Gia esistente'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('show: files_count reflects the linked attachments', function () {
    Sanctum::actingAs(documentBundleUserWith(['view']));
    $documentBundle = DocumentBundle::factory()->create();
    $documentBundle->attach(UploadedFile::fake()->create('doc.pdf', 8, 'application/pdf'), 'documents');
    $documentBundle->attach(UploadedFile::fake()->create('doc2.pdf', 8, 'application/pdf'), 'documents');

    $this->getJson("/api/document-bundles/{$documentBundle->id}")
        ->assertOk()
        ->assertJsonPath('data.files_count', 2);
});

it('update: 200, name/description/is_active editable', function () {
    Sanctum::actingAs(documentBundleUserWith(['update', 'view']));
    $documentBundle = DocumentBundle::factory()->create();

    $this->patchJson("/api/document-bundles/{$documentBundle->id}", ['name' => 'Rinominato', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'Rinominato')
        ->assertJsonPath('data.is_active', false);
});

it('update: 403 without document-bundles.update', function () {
    Sanctum::actingAs(documentBundleUserWith([]));
    $documentBundle = DocumentBundle::factory()->create();

    $this->patchJson("/api/document-bundles/{$documentBundle->id}", ['name' => 'Rinominato'])->assertForbidden();
});

it('destroy: 204, cascades and removes the bundle\'s attachments (AC-004)', function () {
    Sanctum::actingAs(documentBundleUserWith(['delete']));
    $documentBundle = DocumentBundle::factory()->create();
    $documentBundle->attach(UploadedFile::fake()->create('doc.pdf', 8, 'application/pdf'), 'documents');

    $this->deleteJson("/api/document-bundles/{$documentBundle->id}")->assertNoContent();

    $this->assertDatabaseMissing('document_bundles', ['id' => $documentBundle->id]);
    $this->assertDatabaseMissing('attachments', ['attachable_type' => 'document_bundle', 'attachable_id' => $documentBundle->id]);
});

it('destroy: 403 without document-bundles.delete', function () {
    Sanctum::actingAs(documentBundleUserWith([]));
    $documentBundle = DocumentBundle::factory()->create();

    $this->deleteJson("/api/document-bundles/{$documentBundle->id}")->assertForbidden();
});
