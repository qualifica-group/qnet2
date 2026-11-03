<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
});

it('AC-001: answers 403 on every endpoint without the permission and writes nothing', function () {
    $owner = aiapi_user();
    $conversation = aiapi_conversation($owner);
    $message = AiMessage::factory()->for($conversation, 'conversation')->create();
    $attachment = $message->attach(aiapi_pdf());
    $before = AiConversation::query()->count();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/ai-assistant/config')->assertForbidden();
    $this->getJson('/api/ai-assistant/conversations')->assertForbidden();
    $this->postJson('/api/ai-assistant/conversations')->assertForbidden();
    $this->getJson("/api/ai-assistant/conversations/{$conversation->id}")->assertForbidden();
    $this->deleteJson("/api/ai-assistant/conversations/{$conversation->id}")->assertForbidden();
    $this->postJson("/api/ai-assistant/conversations/{$conversation->id}/messages", ['content' => 'x', 'locale' => 'en'])->assertForbidden();
    $this->getJson("/api/ai-assistant/messages/{$message->id}/attachments/{$attachment->id}")->assertForbidden();

    expect(AiConversation::query()->count())->toBe($before)
        ->and(AiMessage::query()->count())->toBe(1);
});

it('answers 401 without authentication', function () {
    $this->getJson('/api/ai-assistant/config')->assertUnauthorized();
});

it('AC-002: exposes config, limits and only the viewable mention modules, never keys or urls', function () {
    config()->set('ai-assistant.max_files', 3);
    Sanctum::actingAs(aiapi_user(['companies.view', 'tasks.view']));

    $response = $this->getJson('/api/ai-assistant/config')->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.driver', 'openai')
        ->assertJsonPath('data.model', 'gpt-x')
        ->assertJsonPath('data.limits.max_files', 3)
        ->assertJsonPath('data.limits.accepted_mimes', config('ai-assistant.accepted_mimes'))
        ->assertJsonPath('data.mention_modules', ['companies', 'tasks']);

    expect($response->getContent())->not->toContain('sk-secret')->not->toContain('llm.test');
});

it('reports not configured when the driver is null', function () {
    config()->set('ai-assistant.driver', null);
    Sanctum::actingAs(aiapi_user());

    $this->getJson('/api/ai-assistant/config')->assertOk()
        ->assertJsonPath('data.configured', false)
        ->assertJsonPath('data.driver', null)
        ->assertJsonPath('data.model', null);
});

it('creates an empty conversation and shows it with its messages in order', function () {
    $user = aiapi_user();
    Sanctum::actingAs($user);

    $id = $this->postJson('/api/ai-assistant/conversations')->assertCreated()
        ->assertJsonPath('data.title', '')
        ->assertJsonPath('data.last_message_at', null)
        ->json('data.id');

    $first = AiMessage::factory()->create(['ai_conversation_id' => $id, 'content' => 'first']);
    AiMessage::factory()->assistant()->create(['ai_conversation_id' => $id, 'content' => 'second']);
    $first->attach(aiapi_pdf('a.pdf'));

    $response = $this->getJson("/api/ai-assistant/conversations/{$id}")->assertOk();

    expect($response->json('data.messages.*.content'))->toBe(['first', 'second'])
        ->and($response->json('data.messages.0.attachments.0.original_name'))->toBe('a.pdf')
        ->and($response->json('data.messages.1.role'))->toBe('assistant');
});

it('lists only own conversations, last_message_at desc with unanswered last, with offset and limit', function () {
    $user = aiapi_user();
    $old = aiapi_conversation($user, ['title' => 'old', 'last_message_at' => now()->subDays(2)]);
    $recent = aiapi_conversation($user, ['title' => 'recent', 'last_message_at' => now()->subHour()]);
    $empty = aiapi_conversation($user, ['title' => '', 'last_message_at' => null]);
    aiapi_conversation(aiapi_user(), ['title' => 'foreign', 'last_message_at' => now()]);
    Sanctum::actingAs($user);

    $all = $this->getJson('/api/ai-assistant/conversations')->assertOk();
    expect($all->json('items.*.id'))->toBe([$recent->id, $old->id, $empty->id])
        ->and($all->json('pagination.total'))->toBe(3);

    $page = $this->getJson('/api/ai-assistant/conversations?offset=1&limit=1')->assertOk();
    expect($page->json('items.*.id'))->toBe([$old->id]);

    $this->getJson('/api/ai-assistant/conversations?limit=0')->assertStatus(422);
});

it('AC-004: another user gets 403 on show, delete, send and download, and never sees the conversation', function () {
    $owner = aiapi_user();
    $conversation = aiapi_conversation($owner);
    $message = AiMessage::factory()->for($conversation, 'conversation')->create();
    $attachment = $message->attach(aiapi_pdf());
    $intruder = aiapi_user();
    Sanctum::actingAs($intruder);

    $this->getJson("/api/ai-assistant/conversations/{$conversation->id}")->assertForbidden();
    $this->deleteJson("/api/ai-assistant/conversations/{$conversation->id}")->assertForbidden();
    $this->getJson("/api/ai-assistant/messages/{$message->id}/attachments/{$attachment->id}")->assertForbidden();
    aiapi_send($intruder, $conversation)->assertForbidden();
    $this->getJson('/api/ai-assistant/conversations')->assertOk()->assertJsonCount(0, 'items');

    expect(AiConversation::query()->whereKey($conversation->id)->exists())->toBeTrue()
        ->and(AiMessage::query()->count())->toBe(1);
});

it('answers 404 for a missing conversation and for an attachment of another message', function () {
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    $message = AiMessage::factory()->for($conversation, 'conversation')->create();
    $other = AiMessage::factory()->for($conversation, 'conversation')->create();
    $foreignFile = $other->attach(aiapi_pdf());
    Sanctum::actingAs($user);

    $this->getJson('/api/ai-assistant/conversations/999999')->assertNotFound();
    $this->getJson("/api/ai-assistant/messages/{$message->id}/attachments/{$foreignFile->id}")->assertNotFound();
});

it('downloads an own attachment', function () {
    $user = aiapi_user();
    $message = AiMessage::factory()->for(aiapi_conversation($user), 'conversation')->create();
    $attachment = $message->attach(aiapi_pdf('report.pdf'));
    Sanctum::actingAs($user);

    $response = $this->get("/api/ai-assistant/messages/{$message->id}/attachments/{$attachment->id}")->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('attachment')->toContain('report.pdf');
});

it('AC-016: DELETE removes the conversation, messages, attachment rows and files', function () {
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    $message = AiMessage::factory()->for($conversation, 'conversation')->create();
    $attachment = $message->attach(aiapi_pdf());
    Storage::disk('local')->assertExists($attachment->path);
    Sanctum::actingAs($user);

    $this->deleteJson("/api/ai-assistant/conversations/{$conversation->id}")->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null);

    expect(AiConversation::query()->count())->toBe(0)
        ->and(AiMessage::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});
