<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
});

it('AC-003: answers 503 with a bare envelope and never calls the provider when the driver is null', function () {
    config()->set('ai-assistant.driver', null);
    Http::fake();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    $response = aiapi_send($user, $conversation)->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message']);

    expect($response->getContent())->not->toContain('llm.test')->not->toContain('sk-secret');
    Http::assertNothingSent();
    expect(AiMessage::query()->count())->toBe(0);
});

it('AC-003: answers 503 for anthropic without api key', function () {
    aiapi_configure('anthropic');
    config()->set('ai-assistant.anthropic.api_key', null);
    Http::fake();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user))->assertStatus(503)->assertJsonPath('success', false);

    Http::assertNothingSent();
    expect(AiMessage::query()->count())->toBe(0);
});

it('AC-005: streams message.created, 3 deltas and message.completed and stores both messages (openai)', function () {
    aiapi_fake_provider();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    $response = aiapi_send($user, $conversation, ['content' => 'Ciao assistente'])->assertOk();
    $events = aiapi_parse_sse($response->streamedContent());

    expect($response->headers->get('content-type'))->toContain('text/event-stream')
        ->and($response->headers->get('x-accel-buffering'))->toBe('no')
        ->and($response->headers->get('cache-control'))->toContain('no-cache')
        ->and(array_column($events, 'event'))->toBe(['message.created', 'delta', 'delta', 'delta', 'message.completed'])
        ->and($events[0]['data']['user_message']['content'])->toBe('Ciao assistente')
        ->and(array_column(array_column(array_slice($events, 1, 3), 'data'), 'text'))->toBe(['Ci', 'ao', '!']);

    $assistant = $events[4]['data']['assistant_message'];
    expect($assistant['role'])->toBe('assistant')
        ->and($assistant['content'])->toBe('Ciao!')
        ->and($events[4]['data']['conversation']['id'])->toBe($conversation->id);

    $stored = AiMessage::query()->where('ai_conversation_id', $conversation->id)->orderBy('id')->get();
    expect($stored)->toHaveCount(2)
        ->and($stored[0]->role->value)->toBe('user')
        ->and($stored[1]->role->value)->toBe('assistant')
        ->and($stored[1]->content)->toBe('Ciao!')
        ->and($stored[1]->model)->toBe('gpt-x')
        ->and($stored[1]->input_tokens)->toBe(11)
        ->and($stored[1]->output_tokens)->toBe(3);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk-secret'));
});

it('AC-006: streams the same sequence with anthropic and sends key, version and cached system', function () {
    aiapi_configure('anthropic');
    aiapi_fake_provider(aiapi_anthropic_body());
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    $events = aiapi_parse_sse(aiapi_send($user, $conversation)->assertOk()->streamedContent());

    expect(array_column($events, 'event'))->toBe(['message.created', 'delta', 'delta', 'delta', 'message.completed']);

    $assistant = AiMessage::query()->where('role', 'assistant')->sole();
    expect($assistant->content)->toBe('Ciao!')
        ->and($assistant->model)->toBe('claude-x')
        ->and($assistant->input_tokens)->toBe(21)
        ->and($assistant->output_tokens)->toBe(7)
        ->and(AiMessage::query()->count())->toBe(2);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'ak-secret')
        && $request->hasHeader('anthropic-version', '2023-06-01'));

    $system = aiapi_sent_payload()['system'];
    expect(end($system)['cache_control'])->toBe(['type' => 'ephemeral']);
});

it('AC-007: sends system (instructions then guide), the last 20 historic messages, then the current turn', function () {
    config()->set('ai-assistant.history_window', 20);
    aiapi_fake_provider();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    foreach (range(1, 25) as $n) {
        $factory = AiMessage::factory()->for($conversation, 'conversation');
        ($n % 2 === 1 ? $factory : $factory->assistant())->create(['content' => "m{$n}"]);
    }

    aiapi_send($user, $conversation, ['content' => 'the 26th', 'page_guide_key' => 'quotes', 'page_guide_text' => 'GUIDE BODY'])
        ->assertOk()->streamedContent();

    $messages = aiapi_sent_payload()['messages'];
    expect($messages)->toHaveCount(22)
        ->and($messages[0]['role'])->toBe('system')
        ->and($messages[1]['content'])->toBe('m6')
        ->and($messages[20]['content'])->toBe('m25')
        ->and(collect($messages)->pluck('content')->implode('|'))->not->toContain('m5|')
        ->and($messages[21]['role'])->toBe('user')
        ->and($messages[21]['content'])->toContain('the 26th');
});

it('AC-008: puts the page guide after the fixed instructions in the system prompt', function () {
    aiapi_fake_provider();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user), ['page_guide_key' => 'quotes', 'page_guide_text' => 'GUIDE BODY'])
        ->assertOk()->streamedContent();

    $system = aiapi_sent_payload()['messages'][0]['content'];
    expect(strpos($system, 'in-app assistant'))->toBeLessThan(strpos($system, 'GUIDE BODY'))
        ->and($system)->toContain('guide: quotes')
        ->and($system)->toContain('Always answer in English');
});

it('AC-008: rejects a guide longer than guide_max_chars with 422', function () {
    config()->set('ai-assistant.guide_max_chars', 10);
    Http::fake();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user), ['page_guide_text' => str_repeat('g', 11)])
        ->assertStatus(422)->assertJsonValidationErrors(['page_guide_text']);

    Http::assertNothingSent();
});

it('AC-014: a provider 500 emits a generic error event and removes the user message and its files', function () {
    aiapi_fake_provider('boom', 500);
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    $raw = aiapi_send($user, $conversation, ['files' => [aiapi_pdf()]])->assertOk()->streamedContent();
    $events = aiapi_parse_sse($raw);

    expect(array_column($events, 'event'))->toBe(['message.created', 'error'])
        ->and($events[1]['data']['message'])->not->toBe('')
        ->and($raw)->not->toContain('llm.test')->not->toContain('sk-secret')->not->toContain('Exception')
        ->and(AiMessage::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and($conversation->fresh()->title)->toBe('')
        ->and($conversation->fresh()->last_message_at)->toBeNull();
});

it('AC-014: a provider timeout behaves like a 500', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));
    $user = aiapi_user();

    $raw = aiapi_send($user, aiapi_conversation($user), ['files' => [aiapi_png()]])->assertOk()->streamedContent();

    expect(array_column(aiapi_parse_sse($raw), 'event'))->toBe(['message.created', 'error'])
        ->and($raw)->not->toContain('timed out')
        ->and(AiMessage::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('AC-014: a stream cut before completion stores no assistant message', function () {
    aiapi_fake_provider('data: '.json_encode(['model' => 'gpt-x', 'choices' => [['delta' => ['content' => 'Ci']]]])."\n\n");
    $user = aiapi_user();

    $events = aiapi_parse_sse(aiapi_send($user, aiapi_conversation($user))->assertOk()->streamedContent());

    expect(array_column($events, 'event'))->toBe(['message.created', 'delta', 'error'])
        ->and(AiMessage::query()->count())->toBe(0);
});

it('AC-015: the first completed message sets the title (60 chars) and last_message_at', function () {
    aiapi_fake_provider();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    $content = str_repeat('abcdefghij', 8);

    aiapi_send($user, $conversation, ['content' => $content])->assertOk()->streamedContent();

    $fresh = $conversation->fresh();
    expect($fresh->title)->toBe(substr($content, 0, 60))
        ->and($fresh->last_message_at)->not->toBeNull();
});

it('AC-015: an existing title is kept', function () {
    aiapi_fake_provider();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user, ['title' => 'Kept']);

    aiapi_send($user, $conversation, ['content' => 'something else'])->assertOk()->streamedContent();

    expect(AiConversation::query()->find($conversation->id)->title)->toBe('Kept');
});
