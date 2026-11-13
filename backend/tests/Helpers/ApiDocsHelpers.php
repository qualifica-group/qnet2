<?php

declare(strict_types=1);

use App\Services\ApiDocs\OpenApiDocumentProvider;
use Illuminate\Support\Facades\Cache;

if (! function_exists('generatedApiDocument')) {
    /**
     * The OpenAPI document, generated once per worker: building it analyses the
     * whole API surface (seconds), so the tests of the area share the result.
     *
     * @return array<string, mixed>
     */
    function generatedApiDocument(): array
    {
        static $document = null;

        return $document ??= app(OpenApiDocumentProvider::class)->generate();
    }
}

if (! function_exists('seedApiDocumentCache')) {
    /**
     * Pre-fills the document cache with the shared document, so an HTTP call in
     * the test serves it without regenerating.
     */
    function seedApiDocumentCache(string $generatedAt = '2026-11-13T00:00:00+00:00'): void
    {
        Cache::forever(OpenApiDocumentProvider::CACHE_KEY, ['document' => generatedApiDocument(), 'generated_at' => $generatedAt]);
    }
}
