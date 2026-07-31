<?php

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithAttachmentAbilities')) {
    function userWithAttachmentAbilities(array $abilities): User
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

// ---------------------------------------------------------------------------
// upload — POST /api/attachments
// ---------------------------------------------------------------------------

it('upload: 201 + stores file on disk + persists metadata', function () {
    $actor = userWithAttachmentAbilities(['create']);
    Sanctum::actingAs($actor);

    $file = UploadedFile::fake()->create('report.pdf', 64, 'application/pdf');

    $response = $this->postJson('/api/attachments', ['file' => $file])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.original_name', 'report.pdf')
        ->assertJsonPath('data.mime_type', 'application/pdf')
        // storage internals are never exposed
        ->assertJsonMissingPath('data.path')
        ->assertJsonMissingPath('data.disk');

    $attachment = Attachment::firstOrFail();
    expect($attachment->uploaded_by)->toBe($actor->id);
    Storage::disk('local')->assertExists($attachment->path);
    expect($response->json('data.download_url'))->toContain("/api/attachments/{$attachment->id}/download");
});

it('upload: 403 without attachments.create', function () {
    $actor = userWithAttachmentAbilities([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
        ->assertForbidden();
});

it('upload: 401 when unauthenticated', function () {
    $this->postJson('/api/attachments', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
        ->assertUnauthorized();
});

it('upload: 422 when file is missing', function () {
    $actor = userWithAttachmentAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('upload: 422 when MIME type is not allowed', function () {
    $actor = userWithAttachmentAbilities(['create']);
    Sanctum::actingAs($actor);

    $file = UploadedFile::fake()->create('evil.exe', 10, 'application/x-msdownload');

    $this->postJson('/api/attachments', ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('upload: links to an allowed polymorphic owner', function () {
    $actor = userWithAttachmentAbilities(['create']);
    Sanctum::actingAs($actor);
    $owner = User::factory()->create();

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        'attachable_type' => 'user',
        'attachable_id' => $owner->id,
        'collection' => 'documents',
    ])->assertCreated()
        ->assertJsonPath('data.attachable_id', $owner->id)
        ->assertJsonPath('data.collection', 'documents');

    expect($owner->attachments()->count())->toBe(1);
});

it('upload: 422 for a non-allowlisted attachable_type', function () {
    $actor = userWithAttachmentAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        'attachable_type' => 'role',
        'attachable_id' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('attachable_type');
});

it('upload: 422 when the polymorphic owner does not exist', function () {
    $actor = userWithAttachmentAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        'attachable_type' => 'user',
        'attachable_id' => 999999,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('attachable_id');
});

// ---------------------------------------------------------------------------
// show — GET /api/attachments/{attachment}
// ---------------------------------------------------------------------------

it('show: 200 with attachments.view', function () {
    $actor = userWithAttachmentAbilities(['view']);
    Sanctum::actingAs($actor);
    $attachment = Attachment::factory()->create();

    $this->getJson("/api/attachments/{$attachment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $attachment->id);
});

it('show: 403 without attachments.view', function () {
    $actor = userWithAttachmentAbilities([]);
    Sanctum::actingAs($actor);
    $attachment = Attachment::factory()->create();

    $this->getJson("/api/attachments/{$attachment->id}")->assertForbidden();
});

// ---------------------------------------------------------------------------
// download — GET /api/attachments/{attachment}/download
// ---------------------------------------------------------------------------

it('download: streams the stored file', function () {
    $actor = userWithAttachmentAbilities(['create', 'view']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('report.pdf', 16, 'application/pdf'),
    ])->assertCreated();

    $attachment = Attachment::firstOrFail();

    $this->get("/api/attachments/{$attachment->id}/download")
        ->assertOk()
        ->assertDownload('report.pdf');
});

it('download: 403 without attachments.view', function () {
    $actor = userWithAttachmentAbilities([]);
    Sanctum::actingAs($actor);
    $attachment = Attachment::factory()->create();

    $this->get("/api/attachments/{$attachment->id}/download")->assertForbidden();
});

// A guest hitting a protected endpoint straight from the browser address bar
// sends `Accept: text/html`, so Authenticate takes the redirect branch. With
// the framework default (`route('login')`) that branch throws
// RouteNotFoundException (500) in this API-only app instead of answering 401
// — see the redirectGuestsTo override in bootstrap/app.php.
it('view: 401 for an unauthenticated browser navigation, never a login redirect', function () {
    $attachment = Attachment::factory()->create();

    $this->get("/api/attachments/{$attachment->id}/view", ['Accept' => 'text/html'])
        ->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// destroy — DELETE /api/attachments/{attachment}
// ---------------------------------------------------------------------------

it('destroy: 204 + removes metadata and binary', function () {
    $actor = userWithAttachmentAbilities(['create', 'delete']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('report.pdf', 16, 'application/pdf'),
    ])->assertCreated();

    $attachment = Attachment::firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();

    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('local')->assertMissing($attachment->path);
});

it('destroy: 403 without attachments.delete', function () {
    $actor = userWithAttachmentAbilities([]);
    Sanctum::actingAs($actor);
    $attachment = Attachment::factory()->create();

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertForbidden();
});
