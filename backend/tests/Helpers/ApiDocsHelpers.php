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
     * Pre-fills the document cache with the shared document under the current
     * signature, so an HTTP call in the test serves it without regenerating.
     */
    function seedApiDocumentCache(): void
    {
        $document = generatedApiDocument();

        Cache::put(OpenApiDocumentProvider::CACHE_KEY_PREFIX.app(OpenApiDocumentProvider::class)->signature(), $document, 600);
    }
}
