<?php

use App\Services\AiAssistant\Settings\OpenRouterCatalog;
use App\Services\AiAssistant\Settings\OpenRouterCatalogUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function negprice_fake(string $prompt): void
{
    Cache::flush();
    Http::fake(['*/models/acme/model/endpoints' => Http::response(['data' => ['endpoints' => [
        ['tag' => 'a', 'supported_parameters' => ['tools'], 'pricing' => ['prompt' => $prompt, 'completion' => '0.000002', 'request' => '0']],
        ['tag' => 'b', 'supported_parameters' => ['tools'], 'pricing' => ['prompt' => '0.000001', 'completion' => '0.000001', 'request' => '0']],
    ]]])]);
}

it('leaves out an endpoint with a negative price instead of letting it lower the reservation', function () {
    negprice_fake('-1');

    $ceiling = app(OpenRouterCatalog::class)->priceCeiling('acme/model', zdrOnly: false);

    expect([$ceiling->promptUsdPerToken, $ceiling->completionUsdPerToken])->toBe(['0.000001000000', '0.000001000000']);
});

it('fails when every endpoint has a negative price', function () {
    Cache::flush();
    Http::fake(['*' => Http::response(['data' => ['endpoints' => [
        ['tag' => 'a', 'supported_parameters' => ['tools'], 'pricing' => ['prompt' => '-1', 'completion' => '-1']],
    ]]])]);

    expect(fn () => app(OpenRouterCatalog::class)->priceCeiling('acme/model', zdrOnly: false))
        ->toThrow(OpenRouterCatalogUnavailableException::class);
});
