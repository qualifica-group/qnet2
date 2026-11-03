<?php

use App\Models\AiMessage;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
});

/**
 * Re-evaluates config/ai-assistant.php with the given AI_ASSISTANT_ACCEPTED_MIMES value.
 *
 * @return list<string>
 */
function ai_local_accepted_mimes(?string $value): array
{
    $previous = getenv('AI_ASSISTANT_ACCEPTED_MIMES');
    $value === null ? putenv('AI_ASSISTANT_ACCEPTED_MIMES') : putenv("AI_ASSISTANT_ACCEPTED_MIMES={$value}");

    try {
        return (require config_path('ai-assistant.php'))['accepted_mimes'];
    } finally {
        $previous === false ? putenv('AI_ASSISTANT_ACCEPTED_MIMES') : putenv("AI_ASSISTANT_ACCEPTED_MIMES={$previous}");
    }
}

it('AC-029: the env can narrow the accepted types but never add unsupported ones', function () {
    expect(ai_local_accepted_mimes(null))->toBe(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
        ->and(ai_local_accepted_mimes(''))->toBe(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
        ->and(ai_local_accepted_mimes('image/png, image/jpeg'))->toBe(['image/png', 'image/jpeg'])
        ->and(ai_local_accepted_mimes('image/png,application/x-msdownload'))->toBe(['image/png']);
});

it('AC-029: a type removed from the accepted list is rejected with 422 before the provider is called', function () {
    config()->set('ai-assistant.accepted_mimes', ['image/png']);
    Http::fake();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user), ['files' => [aiapi_pdf()]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['files.0']);

    Http::assertNothingSent();
    expect(AiMessage::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0);
});

it('AC-029: with no accepted type any file is refused and the config endpoint says so', function () {
    config()->set('ai-assistant.accepted_mimes', []);
    Http::fake();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user), ['files' => [aiapi_png()]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['files']);

    Http::assertNothingSent();
    Sanctum::actingAs($user);
    $this->getJson('/api/ai-assistant/config')->assertOk()->assertJsonPath('data.limits.accepted_mimes', []);
});

it('AC-029: a local server without api key is configured and gets no Authorization header', function () {
    config()->set('ai-assistant.openai.api_key', null);
    config()->set('ai-assistant.openai.base_url', 'http://127.0.0.1:1234/v1');
    aiapi_fake_provider();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user))->assertOk()->streamedContent();

    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:1234/v1/chat/completions'
        && ! $request->hasHeader('Authorization'));
    expect(AiMessage::query()->count())->toBe(2);
});

it('AC-030: reasoning text is never streamed, only the answer', function () {
    $reasoning = 'data: '.json_encode(['model' => 'local', 'choices' => [['delta' => ['reasoning_content' => 'thinking...']]]])."\n\n";
    aiapi_fake_provider($reasoning.aiapi_openai_body(['Ri', 'sposta']));
    $user = aiapi_user();

    $events = aiapi_parse_sse(aiapi_send($user, aiapi_conversation($user))->assertOk()->streamedContent());
    $deltas = array_values(array_filter($events, fn (array $event) => $event['event'] === 'delta'));

    expect(array_map(fn (array $event) => $event['data']['text'], $deltas))->toBe(['Ri', 'sposta'])
        ->and(AiMessage::query()->where('role', 'assistant')->value('content'))->toBe('Risposta');
});

it('AC-030: an answer with no text (budget spent on reasoning) is an error and stores nothing', function () {
    $body = 'data: '.json_encode(['model' => 'local', 'choices' => [['delta' => ['reasoning_content' => 'thinking...']]]])."\n\n"
        .'data: '.json_encode(['model' => 'local', 'choices' => [['delta' => [], 'finish_reason' => 'length']]])."\n\n"
        ."data: [DONE]\n\n";
    aiapi_fake_provider($body);
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    $events = aiapi_parse_sse(aiapi_send($user, $conversation, ['files' => [aiapi_png()]])->assertOk()->streamedContent());

    expect(array_column($events, 'event'))->toBe(['message.created', 'error'])
        ->and(AiMessage::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and($conversation->fresh()->last_message_at)->toBeNull();
});
