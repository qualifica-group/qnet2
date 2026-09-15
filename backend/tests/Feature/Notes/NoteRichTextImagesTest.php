<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\RichText\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Rich text images embedded in a note's body (spec 0128, D-3/D-4)
|--------------------------------------------------------------------------
|
| AC-003/AC-004/AC-005/AC-006: a new inline `data:` image becomes a `rich_text`
| attachment of the note; an invalid one (bad bytes, oversized, over the
| per-save cap) leaves nothing behind; a foreign `data-attachment-id` is
| stripped without touching the attachment it points to; removing an image on
| update deletes its attachment (row + file).
*/

uses(RefreshDatabase::class);

// A minimal, valid 1x1 transparent PNG (67 bytes decoded) — small enough to
// stay well under image_max_kb by default, real enough for finfo to detect
// image/png from the actual bytes (D-3: MIME is verified server-side, never
// trusted from the data: URI's declared type). Guarded (not a plain `const`):
// this constant is shared verbatim with NoteRichTextContentTest.php, and both
// files load into the SAME process when the Notes suite runs together.
if (! defined('VALID_PNG_BASE64')) {
    define('VALID_PNG_BASE64', 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
}

if (! function_exists('noteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function noteActor(array $abilities = []): User
    {
        foreach (['request-management.view', 'request-management.viewAll', 'notes.create'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('noteManagedOpportunity')) {
    function noteManagedOpportunity(User $operator): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);

        return $opportunity;
    }
}

if (! function_exists('pngDataUri')) {
    function pngDataUri(): string
    {
        return 'data:image/png;base64,'.VALID_PNG_BASE64;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

// ---------------------------------------------------------------------------
// AC-003 — a valid inline image becomes a rich_text attachment of the note
// ---------------------------------------------------------------------------

it('AC-003: POST with a valid inline image creates a rich_text attachment of the note and rewrites the tag', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => '<p>Look</p><img src="'.pngDataUri().'" alt="">',
    ])->assertCreated();

    $noteId = $response->json('data.id');
    $attachment = Attachment::query()->where('attachable_type', 'note')->where('attachable_id', $noteId)->first();

    expect($attachment)->not->toBeNull()
        ->and($attachment->collection)->toBe(RichText::ATTACHMENT_COLLECTION);

    $body = $response->json('data.body');
    expect($body)->toContain('data-attachment-id="'.$attachment->id.'"')
        ->and($body)->not->toContain('src=');

    Storage::disk($attachment->disk)->assertExists($attachment->path);
});

// ---------------------------------------------------------------------------
// AC-004 — an invalid image (bad bytes / oversized / over the per-save cap)
// leaves no note, no attachment and no file behind
// ---------------------------------------------------------------------------

it('AC-004: a data URI whose bytes are not actually an image -> 422 body, nothing created', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $countBefore = Note::count();

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => '<p>Look</p><img src="data:image/png;base64,'.base64_encode('not an image').'" alt="">',
    ])->assertStatus(422)->assertJsonValidationErrors('body');

    expect(Note::count())->toBe($countBefore)
        ->and(Attachment::count())->toBe(0);
});

it('AC-004: an image over image_max_kb -> 422 body, nothing created', function () {
    config(['rich_text.image_max_kb' => 0]);

    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => '<p>Look</p><img src="'.pngDataUri().'" alt="">',
    ])->assertStatus(422)->assertJsonValidationErrors('body');

    expect(Note::count())->toBe(0)
        ->and(Attachment::count())->toBe(0);
});

it('AC-004: more than max_new_images in a single save -> 422 body, nothing created', function () {
    config(['rich_text.max_new_images' => 1]);

    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $body = '<p>Two</p><img src="'.pngDataUri().'" alt=""><img src="'.pngDataUri().'" alt="">';

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => $body,
    ])->assertStatus(422)->assertJsonValidationErrors('body');

    expect(Note::count())->toBe(0)
        ->and(Attachment::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-005 — a data-attachment-id pointing to a foreign owner/collection is
// stripped; the foreign attachment is left untouched
// ---------------------------------------------------------------------------

it('AC-005: a data-attachment-id of another note is stripped, the foreign attachment stays intact', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $otherNote = Note::factory()->for($opportunity, 'notable')->create();
    $foreign = Attachment::factory()->for($otherNote, 'attachable')->create(['collection' => RichText::ATTACHMENT_COLLECTION]);
    Storage::disk($foreign->disk)->put($foreign->path, 'fake-image-bytes');

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => '<p>Text</p><img data-attachment-id="'.$foreign->id.'" alt="">',
    ])->assertCreated();

    expect($response->json('data.body'))->not->toContain('data-attachment-id="'.$foreign->id.'"');
    expect(Attachment::find($foreign->id))->not->toBeNull();
    Storage::disk($foreign->disk)->assertExists($foreign->path);
});

// ---------------------------------------------------------------------------
// AC-006 — PATCH removing one of two images deletes it (row + file), keeps
// the other
// ---------------------------------------------------------------------------

it('AC-006: removing one of two images on update deletes its attachment and keeps the other', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => '<p>Two</p><img src="'.pngDataUri().'" alt=""><img src="'.pngDataUri().'" alt="">',
    ])->assertCreated();

    $noteId = $created->json('data.id');
    $attachmentIds = Attachment::query()->where('attachable_type', 'note')->where('attachable_id', $noteId)->pluck('id')->all();
    expect($attachmentIds)->toHaveCount(2);

    [$kept, $removed] = $attachmentIds;
    $removedAttachment = Attachment::find($removed);

    $updated = $this->patchJson("/api/notes/{$noteId}", [
        'body' => '<p>One left</p><img data-attachment-id="'.$kept.'" alt="">',
    ])->assertOk();

    expect($updated->json('data.body'))->toContain('data-attachment-id="'.$kept.'"')
        ->and($updated->json('data.body'))->not->toContain('data-attachment-id="'.$removed.'"');

    expect(Attachment::find($kept))->not->toBeNull()
        ->and(Attachment::find($removed))->toBeNull();
    Storage::disk($removedAttachment->disk)->assertMissing($removedAttachment->path);
});
