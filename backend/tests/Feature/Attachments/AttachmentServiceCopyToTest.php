<?php

use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('copyTo(): physically duplicates the binary onto a new UUID path and links it to the target', function () {
    $uploader = User::factory()->create();
    $sourceOwner = User::factory()->create();
    $targetOwner = User::factory()->create();

    $source = $sourceOwner->attach(UploadedFile::fake()->create('model.pdf', 16, 'application/pdf'), 'documents');

    $copy = app(AttachmentService::class)->copyTo($source, $targetOwner, 'documents', $uploader);

    expect($copy)->toBeInstanceOf(Attachment::class)
        ->and($copy->id)->not->toBe($source->id)
        ->and($copy->path)->not->toBe($source->path)
        ->and($copy->attachable_type)->toBe($targetOwner->getMorphClass())
        ->and($copy->attachable_id)->toBe($targetOwner->id)
        ->and($copy->collection)->toBe('documents')
        ->and($copy->original_name)->toBe($source->original_name)
        ->and($copy->mime_type)->toBe($source->mime_type)
        ->and($copy->extension)->toBe($source->extension)
        ->and($copy->size)->toBe($source->size)
        ->and($copy->uploaded_by)->toBe($uploader->id);

    Storage::disk('local')->assertExists($copy->path);
    Storage::disk('local')->assertExists($source->path);
});

it('copyTo(): falls back to the source collection when none is given', function () {
    $uploader = User::factory()->create();
    $sourceOwner = User::factory()->create();
    $targetOwner = User::factory()->create();

    $source = $sourceOwner->attach(UploadedFile::fake()->create('model.pdf', 4, 'application/pdf'), 'documents');

    $copy = app(AttachmentService::class)->copyTo($source, $targetOwner, null, $uploader);

    expect($copy->collection)->toBe('documents');
});

it('copyTo(): deleting the source attachment leaves the copy intact', function () {
    $uploader = User::factory()->create();
    $sourceOwner = User::factory()->create();
    $targetOwner = User::factory()->create();

    $source = $sourceOwner->attach(UploadedFile::fake()->create('model.pdf', 4, 'application/pdf'), 'documents');
    $copy = app(AttachmentService::class)->copyTo($source, $targetOwner, 'documents', $uploader);

    app(AttachmentService::class)->delete($source->fresh());

    expect(Attachment::query()->find($source->id))->toBeNull();
    Storage::disk('local')->assertMissing($source->path);

    expect(Attachment::query()->find($copy->id))->not->toBeNull();
    Storage::disk('local')->assertExists($copy->path);
});
