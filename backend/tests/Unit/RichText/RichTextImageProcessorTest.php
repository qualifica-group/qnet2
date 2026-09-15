<?php

use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use App\RichText\RichText;
use App\RichText\RichTextImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Touches the database and the filesystem, so bind the full TestCase +
// RefreshDatabase explicitly (Unit suite has no default RefreshDatabase binding).
uses(TestCase::class, RefreshDatabase::class);

function tinyPngDataUri(): string
{
    // A real, minimal 1x1 transparent PNG — decodes and detects as image/png.
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
}

beforeEach(function () {
    Storage::fake(config('attachments.disk'));
});

it('stores a valid inline data:image/png as a rich_text attachment of the owner and rewrites the tag (AC-003)', function () {
    $task = Task::factory()->create();
    $uploader = User::factory()->create();

    $html = '<p>Hi</p><img src="'.tinyPngDataUri().'" alt="pic">';

    $result = app(RichTextImageProcessor::class)->process($html, $task, $uploader, false, 'description');

    expect($result->createdAttachmentIds)->toHaveCount(1);

    $attachment = Attachment::query()->findOrFail($result->createdAttachmentIds[0]);

    expect($attachment->collection)->toBe(RichText::ATTACHMENT_COLLECTION)
        ->and($attachment->attachable_type)->toBe($task->getMorphClass())
        ->and($attachment->attachable_id)->toBe($task->id)
        ->and($attachment->mime_type)->toBe('image/png')
        ->and($attachment->uploaded_by)->toBe($uploader->id);

    expect($result->html)->toContain('data-attachment-id="'.$attachment->id.'"')
        ->not->toContain('src=')
        ->and($result->referencedAttachmentIds)->toBe([$attachment->id]);

    Storage::disk(config('attachments.disk'))->assertExists($attachment->path);
});

it('rejects a data URI whose base64 payload is corrupt, creating nothing (AC-004)', function () {
    $task = Task::factory()->create();
    $uploader = User::factory()->create();

    $html = '<img src="data:image/png;base64,%%%not-base64%%%" alt="">';

    expect(fn () => app(RichTextImageProcessor::class)->process($html, $task, $uploader, false, 'description'))
        ->toThrow(ValidationException::class);

    expect(Attachment::query()->count())->toBe(0)
        ->and(Storage::disk(config('attachments.disk'))->allFiles())->toBe([]);
});

it('rejects a data URI whose real bytes are not an allowed image type, creating nothing (AC-004)', function () {
    $task = Task::factory()->create();
    $uploader = User::factory()->create();

    $html = '<img src="data:image/png;base64,'.base64_encode('this is definitely not an image').'" alt="">';

    expect(fn () => app(RichTextImageProcessor::class)->process($html, $task, $uploader, false, 'description'))
        ->toThrow(ValidationException::class);

    expect(Attachment::query()->count())->toBe(0)
        ->and(Storage::disk(config('attachments.disk'))->allFiles())->toBe([]);
});

it('rejects an image larger than image_max_kb, creating nothing (AC-004)', function () {
    config(['rich_text.image_max_kb' => 0]);

    $task = Task::factory()->create();
    $uploader = User::factory()->create();

    $html = '<img src="'.tinyPngDataUri().'" alt="">';

    expect(fn () => app(RichTextImageProcessor::class)->process($html, $task, $uploader, false, 'description'))
        ->toThrow(ValidationException::class);

    expect(Attachment::query()->count())->toBe(0)
        ->and(Storage::disk(config('attachments.disk'))->allFiles())->toBe([]);
});

it('rejects a save that embeds more new images than max_new_images, creating nothing (AC-004)', function () {
    config(['rich_text.max_new_images' => 1]);

    $task = Task::factory()->create();
    $uploader = User::factory()->create();

    $html = '<img src="'.tinyPngDataUri().'" alt="one"><img src="'.tinyPngDataUri().'" alt="two">';

    expect(fn () => app(RichTextImageProcessor::class)->process($html, $task, $uploader, false, 'description'))
        ->toThrow(ValidationException::class);

    expect(Attachment::query()->count())->toBe(0)
        ->and(Storage::disk(config('attachments.disk'))->allFiles())->toBe([]);
});

it('removes an img referencing an attachment owned by another record, leaving that attachment intact (AC-005)', function () {
    $task = Task::factory()->create();
    $otherTask = Task::factory()->create();
    $uploader = User::factory()->create();

    $foreign = Attachment::factory()->make(['collection' => RichText::ATTACHMENT_COLLECTION]);
    $foreign->attachable()->associate($otherTask);
    $foreign->save();

    $html = '<p>x</p><img data-attachment-id="'.$foreign->id.'" alt="">';

    $result = app(RichTextImageProcessor::class)->process($html, $task, $uploader, false, 'description');

    expect($result->html)->toBe('<p>x</p>')
        ->and($result->referencedAttachmentIds)->toBe([]);

    expect(Attachment::query()->find($foreign->id))->not->toBeNull();
});

it('deleteUnreferenced(): removes the owner\'s rich_text attachments (row + file) not in keepIds (AC-006)', function () {
    $task = Task::factory()->create();
    $uploader = User::factory()->create();
    $processor = app(RichTextImageProcessor::class);

    $first = $processor->process('<img src="'.tinyPngDataUri().'" alt="">', $task, $uploader, false, 'description');
    $second = $processor->process('<img src="'.tinyPngDataUri().'" alt="">', $task, $uploader, false, 'description');

    $keptId = $first->createdAttachmentIds[0];
    $removedId = $second->createdAttachmentIds[0];
    $removedPath = Attachment::query()->findOrFail($removedId)->path;

    $processor->deleteUnreferenced($task, [$keptId]);

    expect(Attachment::query()->find($keptId))->not->toBeNull()
        ->and(Attachment::query()->find($removedId))->toBeNull();

    Storage::disk(config('attachments.disk'))->assertMissing($removedPath);
});
