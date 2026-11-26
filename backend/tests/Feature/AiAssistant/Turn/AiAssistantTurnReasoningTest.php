<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    aiapi_configure();
});

// Spec 0433, D-7: the reasoning is limited explicitly under `max_tokens`, only for a model that declares it.
function reasoning_fake(array $supportedParameters, int $status = 200): void
{
    // The first stub that matches wins: this one goes in before the helper's.
    Http::fake(['openrouter.test/api/v1/models?*' => Http::response(['data' => [
        ['id' => 'deepseek/deepseek-v4-flash-0731', 'supported_parameters' => $supportedParameters],
        ['id' => 'vendor/other', 'supported_parameters' => ['tools', 'reasoning']],
    ]], $status)]);
    aiapi_fake_agent([aiapi_text_body()]);
}

function reasoning_run(): array
{
    $user = aiapi_user();
    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);

    return aiapi_sent('chat/completions')[0];
}

it('limits the reasoning under max_tokens for a model that declares reasoning', function () {
    reasoning_fake(['tools', 'reasoning']);

    $payload = reasoning_run();

    expect($payload['reasoning'])->toBe(['max_tokens' => 2048])
        ->and($payload['max_tokens'])->toBe(4096);
});

it('keeps the reasoning limit strictly under max_tokens whatever the configuration says', function () {
    config()->set('ai-assistant.agent.reasoning_max_tokens', 99999);
    reasoning_fake(['tools', 'reasoning']);

    expect(reasoning_run()['reasoning'])->toBe(['max_tokens' => 4095]);
});

it('sends no reasoning for a model that does not declare it, so require_parameters excludes no provider', function () {
    reasoning_fake(['tools']);

    expect(reasoning_run())->not->toHaveKey('reasoning');
});

it('sends no reasoning when OpenRouter cannot say, and the call still goes out', function () {
    reasoning_fake(['tools', 'reasoning'], status: 500);

    expect(reasoning_run())->not->toHaveKey('reasoning');
});
