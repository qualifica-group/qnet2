<?php

use App\Services\AiAssistant\Settings\OpenRouterCatalog;
use App\Services\AiAssistant\Settings\OpenRouterCatalogUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function aicc_endpoint(string $tag, string $prompt, string $completion, string $request = '0', array $params = ['tools']): array
{
    return [
        'tag' => $tag, 'provider_name' => $tag, 'supported_parameters' => $params,
        'pricing' => ['prompt' => $prompt, 'completion' => $completion, 'request' => $request],
    ];
}

function aicc_fake(): void
{
    Cache::flush();
    Http::fake([
        '*/endpoints/zdr' => Http::response(['data' => [
            ['model_id' => 'acme/model', 'tag' => 'cheap', 'safety_retention' => false],
            ['model_id' => 'acme/model', 'tag' => 'mid', 'safety_retention' => false],
        ]]),
        '*/models/acme/model/endpoints' => Http::response(['data' => ['endpoints' => [
            aicc_endpoint('cheap', '0.00000056', '0.00000176'),
            aicc_endpoint('mid', '0.00000100', '0.00000150', '0.0002'),
            aicc_endpoint('dear', '0.00000700', '0.00000440'),
            aicc_endpoint('no-tools', '0.00009', '0.00009', '0', ['max_tokens']),
        ]]]),
    ]);
}

it('takes the dearest price of each field among the zero-retention endpoints that support tools', function () {
    aicc_fake();

    $ceiling = app(OpenRouterCatalog::class)->priceCeiling('acme/model', zdrOnly: true);

    expect([$ceiling->promptUsdPerToken, $ceiling->completionUsdPerToken, $ceiling->requestUsd])
        ->toBe(['0.000001000000', '0.000001760000', '0.000200000000']);
});

it('takes every endpoint with tools when zero retention is not required', function () {
    aicc_fake();

    $ceiling = app(OpenRouterCatalog::class)->priceCeiling('acme/model', zdrOnly: false);

    expect([$ceiling->promptUsdPerToken, $ceiling->completionUsdPerToken])->toBe(['0.000007000000', '0.000004400000'])
        ->and($ceiling->maxPrice())->toBe(['prompt' => '7', 'completion' => '4.4', 'request' => '0.0002']);
});

it('fails when no endpoint can be priced', function () {
    Cache::flush();
    Http::fake(['*' => Http::response(['data' => ['endpoints' => [], []]])]);

    expect(fn () => app(OpenRouterCatalog::class)->priceCeiling('acme/model', zdrOnly: false))
        ->toThrow(OpenRouterCatalogUnavailableException::class);
});
