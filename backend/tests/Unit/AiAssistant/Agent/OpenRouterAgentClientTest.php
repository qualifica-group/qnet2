<?php

use App\Enums\AiTurnErrorCode;
use App\Services\AiAssistant\Agent\AgentModelException;
use App\Services\AiAssistant\Agent\OpenRouterAgentClient;
use App\Services\AiAssistant\Costs\AiPriceCeiling;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    aiapi_configure(openLinks: false);
});

function agent_client_settings(): AiEffectiveSettings
{
    return new AiEffectiveSettings(true, 'vendor/model-y', '', ['zdr' => true, 'data_collection' => 'deny', 'require_parameters' => true, 'sort' => 'throughput'], null, null);
}

function agent_client_call(string $body, int $status = 200, array $tools = [], ?callable $onText = null)
{
    Http::fake(['openrouter.test/*' => Http::response($body, $status, ['Content-Type' => 'text/event-stream'])]);

    return app(OpenRouterAgentClient::class)->complete([['role' => 'user', 'content' => 'hi']], $tools, 7, agent_client_settings(), agent_client_prices(), $onText);
}

function agent_client_prices(): AiPriceCeiling
{
    return new AiPriceCeiling('0.000001', '0.000002', '0');
}

it('streams the text, reports each fragment and reads usage with cost, cache and reasoning tokens', function () {
    $seen = [];

    $result = agent_client_call(aiapi_text_body(['Ci', 'ao']), onText: function (string $fragment) use (&$seen): void {
        $seen[] = $fragment;
    });

    expect($result->text)->toBe('Ciao')
        ->and($seen)->toBe(['Ci', 'ao'])
        ->and($result->toolCalls)->toBe([])
        ->and($result->finishReason)->toBe('stop')
        ->and($result->model)->toBe('model-x')
        ->and($result->rawUsage['cost'])->toBe(0.0012)
        ->and($result->rawUsage['prompt_tokens_details']['cached_tokens'])->toBe(40);
});

it('sends the routing, session id, tools and the key, and nothing else about the person', function () {
    $tools = [['type' => 'function', 'function' => ['name' => 'list_records', 'description' => 'd', 'parameters' => ['type' => 'object']]]];

    agent_client_call(aiapi_text_body(), tools: $tools);

    Http::assertSent(function (Request $request) use ($tools): bool {
        $payload = $request->data();

        return $request->url() === AIAPI_OPENROUTER_URL.'/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer '.AIAPI_OPENROUTER_KEY)
            && $payload['model'] === 'vendor/model-y'
            && $payload['stream'] === true
            && $payload['session_id'] === '7'
            && $payload['provider'] === ['zdr' => true, 'data_collection' => 'deny', 'require_parameters' => true, 'sort' => 'throughput', 'max_price' => ['prompt' => '1', 'completion' => '2', 'request' => '0']]
            && $payload['tools'] === $tools
            && $payload['parallel_tool_calls'] === false
            && $payload['max_tokens'] === 4096;
    });
});

it('sends no tools key when there are no tools', function () {
    agent_client_call(aiapi_text_body());

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('tools', $request->data()));
});

it('assembles tool call fragments by index, concatenating the arguments only', function () {
    $body = aiapi_sse([
        ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'call_a', 'function' => ['name' => 'list_records', 'arguments' => '{"dom']]]]]]],
        ['choices' => [['delta' => ['tool_calls' => [['index' => 1, 'id' => 'call_b', 'function' => ['name' => 'run_read_code', 'arguments' => '{"code":']]]]]]],
        ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['name' => '', 'arguments' => 'ain":"x"}']]]]]]],
        ['choices' => [['delta' => ['tool_calls' => [['index' => 1, 'function' => ['arguments' => '"1"}']]]]]]],
        ['choices' => [['delta' => [], 'finish_reason' => 'tool_calls']]],
    ]);

    $result = agent_client_call($body);

    expect($result->finishReason)->toBe('tool_calls')
        ->and($result->toolCalls)->toBe([
            ['id' => 'call_a', 'name' => 'list_records', 'arguments' => '{"domain":"x"}'],
            ['id' => 'call_b', 'name' => 'run_read_code', 'arguments' => '{"code":"1"}'],
        ]);
});

it('keeps the reasoning blocks exactly as they arrived, in order', function () {
    $first = ['type' => 'reasoning.encrypted', 'data' => 'abc', 'id' => 'r1', 'format' => 'x', 'index' => 0];
    $second = ['type' => 'reasoning.text', 'text' => 'thinking', 'signature' => 's', 'index' => 1];

    $result = agent_client_call(aiapi_tool_body('list_records', ['{}'], reasoningDetails: [$first, $second]));

    expect($result->reasoningDetails)->toBe([$first, $second]);
});

it('fails with stream_error on an error in the middle of a 200 stream, without leaking its text', function () {
    Log::spy();
    $body = aiapi_sse([
        ['choices' => [['delta' => ['content' => 'Ci']]]],
        ['error' => ['code' => 429, 'message' => 'SECRET PROVIDER TEXT', 'metadata' => ['error_type' => 'rate_limit_exceeded']], 'choices' => [['delta' => [], 'finish_reason' => 'error']]],
    ]);

    try {
        agent_client_call($body);
        $this->fail('expected an exception');
    } catch (AgentModelException $exception) {
        expect($exception->errorCode)->toBe(AiTurnErrorCode::StreamError)->and($exception->getMessage())->not->toContain('SECRET');
    }

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => ! str_contains(json_encode($context), 'SECRET'));
});

it('fails with stream_error on finish_reason error, a stream cut before the end and malformed json', function (Closure $body) {
    try {
        agent_client_call($body());
        $this->fail('expected an exception');
    } catch (AgentModelException $exception) {
        expect($exception->errorCode)->toBe(AiTurnErrorCode::StreamError);
    }
})->with([
    'finish_reason error' => [fn () => aiapi_sse([['choices' => [['delta' => [], 'finish_reason' => 'error']]]])],
    'cut stream' => [fn () => 'data: '.json_encode(['choices' => [['delta' => ['content' => 'Ci']]]])."\n\n"],
    'malformed json' => [fn () => "data: {not json\n\n"],
]);

it('fails with model_unavailable on an HTTP error and on a connection error', function () {
    foreach ([fn () => agent_client_call('boom', 500), function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out SECRET'));

        return app(OpenRouterAgentClient::class)->complete([['role' => 'user', 'content' => 'hi']], [], 1, agent_client_settings(), agent_client_prices());
    }] as $attempt) {
        try {
            $attempt();
            $this->fail('expected an exception');
        } catch (AgentModelException $exception) {
            expect($exception->errorCode)->toBe(AiTurnErrorCode::ModelUnavailable)->and($exception->getMessage())->not->toContain('SECRET');
        }
    }
});

it('is configured only with a key', function () {
    expect(OpenRouterAgentClient::isConfigured())->toBeTrue();

    config()->set('ai-assistant.openrouter.api_key', null);

    expect(OpenRouterAgentClient::isConfigured())->toBeFalse();
});
