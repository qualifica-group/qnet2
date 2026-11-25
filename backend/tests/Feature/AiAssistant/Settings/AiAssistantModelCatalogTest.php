<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const AIC_KEY = 'sk-or-fake-catalog-key-1111';

function aic_endpoint(string $tag, array $extra = []): array
{
    return $extra + [
        'tag' => $tag,
        'provider_name' => ucfirst($tag),
        'supported_parameters' => ['tools', 'max_tokens'],
        'uptime_last_30m' => 99.5,
        'latency_last_30m' => null,
        'throughput_last_30m' => null,
        'pricing' => ['prompt' => '0.00000007', 'completion' => '0.00000014', 'input_cache_read' => '0.000000014'],
    ];
}

function aic_fake(): void
{
    Cache::flush();
    config()->set('ai-assistant.openrouter.base_url', 'https://openrouter.test/api/v1');
    config()->set('ai-assistant.openrouter.api_key', AIC_KEY);

    Http::fake([
        'openrouter.test/api/v1/models/acme/text-pro/endpoints' => Http::response(['data' => ['endpoints' => [
            aic_endpoint('fast', ['latency_last_30m' => ['p50' => 312.0, 'p90' => 900.0], 'throughput_last_30m' => ['p50' => 193.5]]),
            aic_endpoint('keeps-data'),
            aic_endpoint('safety'),
            aic_endpoint('no-tools', ['supported_parameters' => ['max_tokens']]),
        ]]]),
        'openrouter.test/api/v1/endpoints/zdr' => Http::response(['data' => [
            ['model_id' => 'acme/text-pro', 'tag' => 'fast', 'safety_retention' => false],
            ['model_id' => 'acme/text-pro', 'tag' => 'safety', 'safety_retention' => true],
            ['model_id' => 'acme/text-pro', 'tag' => 'no-tools', 'safety_retention' => false],
            ['model_id' => 'other/model', 'tag' => 'keeps-data', 'safety_retention' => false],
        ]]),
        'openrouter.test/api/v1/models*' => Http::response(['data' => ais_models_body()['data']]),
    ]);
}

beforeEach(function () {
    Sanctum::actingAs(ais_super_admin());
    aic_fake();
});

it('lists only tool-capable text-only models, prices per million, selected flag and pagination', function () {
    $response = $this->getJson('/api/ai-assistant/models')->assertOk();

    expect(array_column($response->json('items'), 'id'))
        ->toBe(['acme/text-pro', 'deepseek/deepseek-v4-flash-0731'])
        ->and($response->json('items.0.pricing'))->toBe(['prompt' => '0.14', 'completion' => '0.28', 'input_cache_read' => '0.014', 'request' => null])
        ->and($response->json('items.0.context_length'))->toBe(131072)
        ->and($response->json('items.0.selected'))->toBeFalse()
        ->and($response->json('items.1.selected'))->toBeTrue()
        ->and($response->json('pagination.total'))->toBe(2);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'supported_parameters=tools'));
});

it('searches by id or name and windows the result', function () {
    $this->getJson('/api/ai-assistant/models?search=DEEPSEEK')->assertOk()
        ->assertJsonCount(1, 'items')->assertJsonPath('pagination.total', 1);

    $this->getJson('/api/ai-assistant/models?offset=1&limit=1')->assertOk()
        ->assertJsonPath('items.0.id', 'deepseek/deepseek-v4-flash-0731')->assertJsonPath('pagination.limit', 1);

    $this->getJson('/api/ai-assistant/models?limit=101')->assertUnprocessable();
});

it('caches the list for a few minutes: the second call does not reach OpenRouter', function () {
    $this->getJson('/api/ai-assistant/models')->assertOk();
    $this->getJson('/api/ai-assistant/models?search=acme')->assertOk();

    Http::assertSentCount(1);
});

it('answers 503 with the fixed text when OpenRouter fails, without leaking the key', function () {
    config()->set('ai-assistant.openrouter.base_url', 'https://down.test/api/v1');
    Http::fake(['down.test/*' => Http::response('upstream said '.AIC_KEY, 500)]);

    $response = $this->getJson('/api/ai-assistant/models')->assertStatus(503)
        ->assertJsonPath('message', 'The model list is not available. Try again later.');
    expect($response->getContent())->not->toContain(AIC_KEY);
});

it('lists providers without data retention that support tools, with speed and latency when given', function () {
    $response = $this->getJson('/api/ai-assistant/models/providers?model_id=acme/text-pro')->assertOk();

    expect($response->json('data'))->toEqual([[
        'name' => 'Fast', 'tag' => 'fast', 'zdr' => true, 'uptime_pct' => 99.5,
        'throughput_tps' => 193.5, 'latency_ms' => 312.0,
        'pricing' => ['prompt' => '0.07', 'completion' => '0.14', 'input_cache_read' => '0.014'],
    ]]);
});

it('rejects a model id that is not author/slug before any request leaves', function () {
    Http::fake();

    foreach (['', 'plain', '../etc/passwd', 'a/b/c', 'a/b?x=1', 'a/..%2F'] as $bad) {
        $this->getJson('/api/ai-assistant/models/providers?model_id='.urlencode($bad))->assertUnprocessable();
    }
    Http::assertNothingSent();
});
