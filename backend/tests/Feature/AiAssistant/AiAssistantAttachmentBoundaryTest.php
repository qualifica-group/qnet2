<?php

use App\Models\AiMessage;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->message = AiMessage::factory()->create();
    $this->attachment = $this->message->attach(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'));

    $this->intruder = User::factory()->create();
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        $this->intruder->givePermissionTo(Permission::findOrCreate("attachments.{$ability}"));
    }
    Sanctum::actingAs($this->intruder);
});

it('stores a file on an AiMessage without being in attachable_types', function () {
    expect(config('attachments.attachable_types'))->not->toHaveKey('ai_message')
        ->and($this->attachment->attachable_type)->toBe('ai_message')
        ->and($this->message->attachments()->count())->toBe(1);

    Storage::disk('local')->assertExists($this->attachment->path);
});

it('keeps the generic attachment endpoints closed on AI message files', function () {
    $id = $this->attachment->id;

    $this->getJson("/api/attachments?attachable_type=ai_message&attachable_id={$this->message->id}")
        ->assertStatus(422);
    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        'attachable_type' => 'ai_message',
        'attachable_id' => $this->message->id,
    ])->assertStatus(422);
    $this->getJson("/api/attachments/{$id}")->assertForbidden();
    $this->getJson("/api/attachments/{$id}/download")->assertForbidden();
    $this->deleteJson("/api/attachments/{$id}")->assertForbidden();

    expect(Attachment::query()->whereKey($id)->exists())->toBeTrue();
});
