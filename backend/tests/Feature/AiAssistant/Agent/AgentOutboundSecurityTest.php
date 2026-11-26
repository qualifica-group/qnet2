<?php

use App\Enums\AiTurnErrorCode;
use App\Services\AiAssistant\Agent\AgentModelException;
use App\Services\AiAssistant\Agent\ConnectorClient;
use App\Services\AiAssistant\Agent\ConnectorUnavailableException;
use App\Services\AiAssistant\Agent\OpenRouterAgentClient;
use App\Services\AiAssistant\Costs\AiPriceCeiling;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    aiapi_configure(openLinks: false);
    Cache::flush();
});

// Rete in uscita (security.md): a 3xx is never followed, so the secret and the credential never reach another address.
it('does not follow a redirect of the connector and closes the call with the fixed error', function () {
    $options = [];
    Http::fake(['connector.test/*' => function ($request, array $requestOptions) use (&$options) {
        $options[] = $requestOptions['allow_redirects'] ?? null;

        return Http::response('', 302, ['Location' => 'http://evil.test/steal']);
    }]);

    expect(fn () => app(ConnectorClient::class)->tools('credential'))->toThrow(ConnectorUnavailableException::class)
        ->and($options)->toBe([false]);
});

it('does not follow a redirect of OpenRouter and closes the call with model_unavailable', function () {
    $options = [];
    Http::fake(['openrouter.test/*' => function ($request, array $requestOptions) use (&$options) {
        $options[] = $requestOptions['allow_redirects'] ?? null;

        return Http::response('', 307, ['Location' => 'http://evil.test/steal']);
    }]);
    $settings = new AiEffectiveSettings(true, 'vendor/model-y', '', ['zdr' => true, 'data_collection' => 'deny', 'require_parameters' => true, 'sort' => 'throughput'], null, null);

    try {
        app(OpenRouterAgentClient::class)->complete([['role' => 'user', 'content' => 'hi']], [], 1, $settings, new AiPriceCeiling('0.000001', '0.000002', '0'));
        $this->fail('expected an exception');
    } catch (AgentModelException $exception) {
        expect($exception->errorCode)->toBe(AiTurnErrorCode::ModelUnavailable)->and($options)->toBe([false]);
    }
});

it('refuses to send the secret and the credential over plain http to a host that is not loopback', function (string $url) {
    Log::spy();
    Http::fake();
    config()->set('ai-assistant.connector.url', $url);

    expect(fn () => app(ConnectorClient::class)->tools('credential'))->toThrow(ConnectorUnavailableException::class);

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => ($context['reason'] ?? null) === 'insecure_url'
        && ! str_contains(json_encode($context), 'example.com'));
})->with(['http://connector.example.com:3000', 'http://10.0.0.5:3000', 'http://127.0.0.1.evil.test']);

it('sends to loopback over http and to any host over https', function (string $url) {
    Http::fake(['*' => Http::response(aiapi_connector_tools(), 200, ['Content-Type' => 'text/event-stream'])]);
    config()->set('ai-assistant.connector.url', $url);

    expect(app(ConnectorClient::class)->tools('credential'))->not->toBe([]);
})->with(['http://127.0.0.1:3000', 'http://localhost:3000', 'http://[::1]:3000', 'https://connector.example.com']);
