<?php

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('orders messages by id and casts role, mentions and tokens', function () {
    $conversation = AiConversation::factory()->create();
    $first = AiMessage::factory()->for($conversation, 'conversation')->create([
        'mentions' => [['module' => 'companies', 'id' => 7, 'label' => 'Acme']],
    ]);
    $second = AiMessage::factory()->assistant()->for($conversation, 'conversation')->create();

    $messages = $conversation->fresh()->messages;

    expect($messages->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($messages[0]->role)->toBe(AiMessageRole::User)
        ->and($messages[0]->mentions[0]['module'])->toBe('companies')
        ->and($messages[1]->role)->toBe(AiMessageRole::Assistant)
        ->and($messages[1]->input_tokens)->toBeInt()
        ->and($conversation->last_message_at)->not->toBeNull()
        ->and($conversation->user)->toBeInstanceOf(User::class);
});

it('cascades conversation rows when the user is deleted', function () {
    $conversation = AiConversation::factory()->create();
    AiMessage::factory()->for($conversation, 'conversation')->create();

    $conversation->user->delete();

    expect(AiConversation::query()->count())->toBe(0)
        ->and(AiMessage::query()->count())->toBe(0);
});

it('AC-016: deleteWithFiles removes messages, attachment rows and files from disk', function () {
    Storage::fake('local');
    $conversation = AiConversation::factory()->create();
    $message = AiMessage::factory()->for($conversation, 'conversation')->create();

    $attachment = $message->attach(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'));
    Storage::disk('local')->assertExists($attachment->path);

    $conversation->deleteWithFiles();

    Storage::disk('local')->assertMissing($attachment->path);
    expect(AiConversation::query()->count())->toBe(0)
        ->and(AiMessage::query()->count())->toBe(0)
        ->and($attachment->newQuery()->whereKey($attachment->id)->exists())->toBeFalse();
});
