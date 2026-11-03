<?php

use App\Services\AiAssistant\Drivers\AiAssistantNotConfiguredException;
use App\Services\AiAssistant\Drivers\AiChatDriverManager;
use App\Services\AiAssistant\Drivers\AiProviderException;
use App\Services\AiAssistant\Drivers\AiStreamEvent;
use App\Services\AiAssistant\Drivers\AnthropicDriver;
use App\Services\AiAssistant\Drivers\OpenAiCompatibleDriver;
use App\Services\AiAssistant\Drivers\SseLineReader;
use App\Services\AiAssistant\Prompt\AiChatPrompt;
use App\Services\AiAssistant\Prompt\AiPromptFile;
use App\Services\AiAssistant\Prompt\AiPromptMessage;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

function ai_prompt(array $history = [], ?AiPromptMessage $current = null, ?string $guide = 'GUIDE'): AiChatPrompt
{
    return new AiChatPrompt('INSTRUCTIONS', $guide, $history, $current ?? new AiPromptMessage('user', 'hello'), 'it');
}

function ai_collect(Generator $stream): array
{
    return iterator_to_array($stream, false);
}

function ai_openai_body(): string
{
    return implode('', [
        'data: '.json_encode(['model' => 'gpt-x', 'choices' => [['delta' => ['content' => 'Ci']]]])."\n\n",
        'data: '.json_encode(['model' => 'gpt-x', 'choices' => [['delta' => ['content' => 'ao']]]])."\n\n",
        'data: '.json_encode(['model' => 'gpt-x', 'choices' => [['delta' => ['content' => '!'], 'finish_reason' => 'stop']]])."\n\n",
        'data: '.json_encode(['model' => 'gpt-x', 'choices' => [], 'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 3]])."\n\n",
        "data: [DONE]\n\n",
    ]);
}

function ai_anthropic_body(): string
{
    $events = [
        ['message_start', ['type' => 'message_start', 'message' => ['model' => 'claude-x', 'usage' => ['input_tokens' => 21, 'output_tokens' => 1]]]],
        ['content_block_delta', ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Ci']]],
        ['content_block_delta', ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'ao']]],
        ['content_block_delta', ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => '!']]],
        ['message_delta', ['type' => 'message_delta', 'usage' => ['output_tokens' => 7]]],
        ['message_stop', ['type' => 'message_stop']],
    ];

    return implode('', array_map(fn ($e) => "event: {$e[0]}\ndata: ".json_encode($e[1])."\n\n", $events));
}

beforeEach(function () {
    config()->set('ai-assistant.openai', ['base_url' => 'https://llm.test/v1', 'api_key' => 'sk-secret', 'model' => 'gpt-x']);
    config()->set('ai-assistant.anthropic', ['base_url' => 'https://claude.test/v1', 'api_key' => 'ak-secret', 'model' => 'claude-x', 'version' => '2023-06-01']);
    config()->set('ai-assistant.max_output_tokens', 512);
    config()->set('ai-assistant.temperature', null);
    config()->set('ai-assistant.timeout_seconds', 30);
    config()->set('ai-assistant.connect_timeout_seconds', 5);
});

it('streams deltas then completed from an OpenAI-compatible provider', function () {
    Http::fake(['llm.test/*' => Http::response(ai_openai_body(), 200, ['Content-Type' => 'text/event-stream'])]);

    $events = ai_collect((new OpenAiCompatibleDriver)->stream(ai_prompt()));

    expect(array_map(fn ($e) => $e->type, $events))->toBe(['delta', 'delta', 'delta', 'completed'])
        ->and(implode('', array_map(fn ($e) => $e->text, array_slice($events, 0, 3))))->toBe('Ciao!')
        ->and($events[3]->model)->toBe('gpt-x')
        ->and($events[3]->inputTokens)->toBe(11)
        ->and($events[3]->outputTokens)->toBe(3);
});

it('builds the OpenAI request: url, bearer, system order, token cap, no temperature', function () {
    Http::fake(['llm.test/*' => Http::response(ai_openai_body())]);
    $history = [new AiPromptMessage('user', 'q1'), new AiPromptMessage('assistant', 'a1')];

    ai_collect((new OpenAiCompatibleDriver)->stream(ai_prompt($history)));

    Http::assertSent(function (Request $r) {
        $b = $r->data();

        return $r->url() === 'https://llm.test/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer sk-secret')
            && $b['stream'] === true
            && $b['stream_options'] === ['include_usage' => true]
            && $b['max_completion_tokens'] === 512
            && ! array_key_exists('temperature', $b)
            && $b['messages'][0] === ['role' => 'system', 'content' => "INSTRUCTIONS\n\nGUIDE"]
            && array_column($b['messages'], 'content') === ["INSTRUCTIONS\n\nGUIDE", 'q1', 'a1', 'hello'];
    });
});

it('omits the bearer header with an empty OpenAI api key and sends temperature when set', function () {
    config()->set('ai-assistant.openai.api_key', '');
    config()->set('ai-assistant.temperature', 0.2);
    Http::fake(['llm.test/*' => Http::response(ai_openai_body())]);

    ai_collect((new OpenAiCompatibleDriver)->stream(ai_prompt(guide: null)));

    Http::assertSent(fn (Request $r) => ! $r->hasHeader('Authorization')
        && $r->data()['temperature'] === 0.2
        && $r->data()['messages'][0]['content'] === 'INSTRUCTIONS');
});

it('sends files to OpenAI as image_url data URI and file parts', function () {
    Http::fake(['llm.test/*' => Http::response(ai_openai_body())]);
    $current = new AiPromptMessage('user', 'see', [
        new AiPromptFile('a.png', 'image/png', 'AAA'),
        new AiPromptFile('b.pdf', 'application/pdf', 'BBB'),
    ]);

    ai_collect((new OpenAiCompatibleDriver)->stream(ai_prompt(current: $current)));

    Http::assertSent(fn (Request $r) => $r->data()['messages'][1]['content'] === [
        ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,AAA']],
        ['type' => 'file', 'file' => ['filename' => 'b.pdf', 'file_data' => 'data:application/pdf;base64,BBB']],
        ['type' => 'text', 'text' => 'see'],
    ]);
});

it('streams deltas then completed from Anthropic', function () {
    Http::fake(['claude.test/*' => Http::response(ai_anthropic_body(), 200, ['Content-Type' => 'text/event-stream'])]);

    $events = ai_collect((new AnthropicDriver)->stream(ai_prompt()));

    expect(array_map(fn ($e) => $e->type, $events))->toBe(['delta', 'delta', 'delta', 'completed'])
        ->and($events[3]->model)->toBe('claude-x')
        ->and($events[3]->inputTokens)->toBe(21)
        ->and($events[3]->outputTokens)->toBe(7);
});

it('builds the Anthropic request with headers and cache_control on the last system block', function () {
    Http::fake(['claude.test/*' => Http::response(ai_anthropic_body())]);

    ai_collect((new AnthropicDriver)->stream(ai_prompt()));

    Http::assertSent(function (Request $r) {
        $b = $r->data();

        return $r->url() === 'https://claude.test/v1/messages'
            && $r->hasHeader('x-api-key', 'ak-secret')
            && $r->hasHeader('anthropic-version', '2023-06-01')
            && $b['max_tokens'] === 512
            && $b['system'] === [
                ['type' => 'text', 'text' => 'INSTRUCTIONS'],
                ['type' => 'text', 'text' => 'GUIDE', 'cache_control' => ['type' => 'ephemeral']],
            ];
    });
});

it('puts cache_control on the instructions block when there is no page guide', function () {
    Http::fake(['claude.test/*' => Http::response(ai_anthropic_body())]);

    ai_collect((new AnthropicDriver)->stream(ai_prompt(guide: null)));

    Http::assertSent(fn (Request $r) => $r->data()['system'] === [
        ['type' => 'text', 'text' => 'INSTRUCTIONS', 'cache_control' => ['type' => 'ephemeral']],
    ]);
});

it('sends files to Anthropic as image and document blocks before the text', function () {
    Http::fake(['claude.test/*' => Http::response(ai_anthropic_body())]);
    $current = new AiPromptMessage('user', 'see', [
        new AiPromptFile('a.png', 'image/png', 'AAA'),
        new AiPromptFile('b.pdf', 'application/pdf', 'BBB'),
    ]);

    ai_collect((new AnthropicDriver)->stream(ai_prompt(current: $current)));

    Http::assertSent(fn (Request $r) => $r->data()['messages'][0]['content'] === [
        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'AAA']],
        ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => 'BBB']],
        ['type' => 'text', 'text' => 'see'],
    ]);
});

it('normalizes Anthropic history: drops a leading assistant and merges same-role turns', function () {
    Http::fake(['claude.test/*' => Http::response(ai_anthropic_body())]);
    $history = [
        new AiPromptMessage('assistant', 'orphan'),
        new AiPromptMessage('user', 'u1'),
        new AiPromptMessage('assistant', 'a1'),
        new AiPromptMessage('user', 'u2'),
    ];

    ai_collect((new AnthropicDriver)->stream(ai_prompt($history)));

    Http::assertSent(fn (Request $r) => $r->data()['messages'] === [
        ['role' => 'user', 'content' => 'u1'],
        ['role' => 'assistant', 'content' => 'a1'],
        ['role' => 'user', 'content' => "u2\n\nhello"],
    ]);
});

it('raises a provider exception on an Anthropic error event', function () {
    $body = "event: error\ndata: ".json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'secret detail']])."\n\n";
    Http::fake(['claude.test/*' => Http::response($body)]);

    expect(fn () => ai_collect((new AnthropicDriver)->stream(ai_prompt())))
        ->toThrow(AiProviderException::class, AiProviderException::GENERIC_MESSAGE);
});

it('maps HTTP 500 to a generic exception without leaking url or key, and logs sanitized', function () {
    Http::fake(['llm.test/*' => Http::response('{"error":"boom sk-secret"}', 500)]);
    Log::spy();

    try {
        ai_collect((new OpenAiCompatibleDriver)->stream(ai_prompt()));
        $this->fail('expected exception');
    } catch (AiProviderException $e) {
        expect($e->getMessage())->not->toContain('llm.test')->not->toContain('sk-secret');
    }

    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($m, $ctx) => ! str_contains(json_encode($ctx), 'sk-secret')
        && ! str_contains(json_encode($ctx), 'llm.test'));
});

it('maps a connection failure to a generic exception', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: https://llm.test/v1 timed out'));

    try {
        ai_collect((new OpenAiCompatibleDriver)->stream(ai_prompt()));
        $this->fail('expected exception');
    } catch (AiProviderException $e) {
        expect($e->getMessage())->toBe(AiProviderException::GENERIC_MESSAGE)->not->toContain('llm.test');
    }
});

it('maps malformed JSON and a truncated stream to a provider exception', function () {
    Http::fake(['llm.test/*' => Http::response("data: {not json\n\n")]);
    expect(fn () => ai_collect((new OpenAiCompatibleDriver)->stream(ai_prompt())))->toThrow(AiProviderException::class);

    Http::fake(['llm.test/*' => Http::response('data: '.json_encode(['choices' => [['delta' => ['content' => 'x']]]])."\n\n")]);
    expect(fn () => ai_collect((new OpenAiCompatibleDriver)->stream(ai_prompt())))->toThrow(AiProviderException::class);
});

it('reassembles SSE events split across chunks and handles CRLF', function () {
    $payload = "event: a\r\ndata: {\"x\":\r\ndata: 1}\r\n\r\n: comment\n\ndata: second\n\n";
    $chunks = str_split($payload, 3);
    $stream = Utils::streamFor((function () use ($chunks) {
        yield from $chunks;
    })());

    $events = iterator_to_array(SseLineReader::events($stream), false);

    expect($events)->toBe([
        ['event' => 'a', 'data' => "{\"x\":\n1}"],
        ['event' => null, 'data' => 'second'],
    ]);
});

it('builds the driver from config and reports configuration state', function () {
    $manager = new AiChatDriverManager;

    config()->set('ai-assistant.driver', null);
    expect($manager->isConfigured())->toBeFalse()->and($manager->driverName())->toBeNull()->and($manager->model())->toBeNull()
        ->and(fn () => $manager->driver())->toThrow(AiAssistantNotConfiguredException::class);

    config()->set('ai-assistant.driver', 'openai');
    config()->set('ai-assistant.openai.api_key', '');
    expect($manager->isConfigured())->toBeTrue()->and($manager->model())->toBe('gpt-x')
        ->and($manager->driver())->toBeInstanceOf(OpenAiCompatibleDriver::class);

    config()->set('ai-assistant.driver', 'anthropic');
    expect($manager->isConfigured())->toBeTrue()->and($manager->model())->toBe('claude-x')
        ->and($manager->driver())->toBeInstanceOf(AnthropicDriver::class);

    config()->set('ai-assistant.anthropic.api_key', '');
    expect($manager->isConfigured())->toBeFalse()
        ->and(fn () => $manager->driver())->toThrow(AiAssistantNotConfiguredException::class);

    config()->set('ai-assistant.driver', 'bogus');
    expect($manager->isConfigured())->toBeFalse();
});

it('registers the manager as a singleton', function () {
    expect(app(AiChatDriverManager::class))->toBe(app(AiChatDriverManager::class));
});

it('exposes delta and completed named constructors', function () {
    expect(AiStreamEvent::delta('x')->type)->toBe('delta')
        ->and(AiStreamEvent::completed('m', 1, 2)->outputTokens)->toBe(2);
});
