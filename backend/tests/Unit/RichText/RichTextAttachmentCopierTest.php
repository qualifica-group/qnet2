<?php

use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use App\RichText\RichText;
use App\RichText\RichTextAttachmentCopier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

// Touches the database and the filesystem, so bind the full TestCase +
// RefreshDatabase explicitly (Unit suite has no default RefreshDatabase binding).
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('attachments.disk'));
});

it('returns null unchanged (no html to copy from)', function () {
    $copier = app(RichTextAttachmentCopier::class);
    $source = Task::factory()->create();
    $target = Task::factory()->create();
    $uploader = User::factory()->create();

    expect($copier->copy(null, $source, $target, $uploader))->toBeNull();
});

it('copies a rich_text image of the source onto the target and rewrites its id (D-8)', function () {
    $source = Task::factory()->create();
    $target = Task::factory()->create();
    $uploader = User::factory()->create();

    $original = Attachment::factory()->make([
        'collection' => RichText::ATTACHMENT_COLLECTION,
        'path' => 'attachments/'.Str::uuid().'.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
    ]);
    $original->attachable()->associate($source);
    $original->save();
    Storage::disk($original->disk)->put($original->path, 'fake-bytes');

    $html = '<p>x</p><img data-attachment-id="'.$original->id.'" alt="pic">';

    $result = app(RichTextAttachmentCopier::class)->copy($html, $source, $target, $uploader);

    $copy = Attachment::query()
        ->where('attachable_type', $target->getMorphClass())
        ->where('attachable_id', $target->id)
        ->where('collection', RichText::ATTACHMENT_COLLECTION)
        ->sole();

    expect($copy->id)->not->toBe($original->id)
        ->and($copy->uploaded_by)->toBe($uploader->id)
        ->and($result)->toBe('<p>x</p><img data-attachment-id="'.$copy->id.'" alt="pic">');

    Storage::disk($copy->disk)->assertExists($copy->path);
});

it('removes an img id with no matching source attachment (D-8)', function () {
    $source = Task::factory()->create();
    $target = Task::factory()->create();
    $uploader = User::factory()->create();

    $html = '<p>x</p><img data-attachment-id="999999" alt="">';

    expect(app(RichTextAttachmentCopier::class)->copy($html, $source, $target, $uploader))->toBe('<p>x</p>');
});
