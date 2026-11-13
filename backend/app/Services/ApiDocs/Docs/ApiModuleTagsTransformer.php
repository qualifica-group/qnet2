<?php

namespace App\Services\ApiDocs\Docs;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Tag;

/**
 * Groups the operations by module, the first path segment (`leads`, `work-orders`),
 * instead of one tag per controller, and gives every module its description
 * (ApiModuleDescriptions) plus, when a table domain of the same name exists, the
 * endpoint that lists its records. Postman folders follow the same grouping.
 */
class ApiModuleTagsTransformer implements DocumentTransformer
{
    private const string ROOT_MODULE = 'root';

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        // Step 1: tag every operation with its module
        $modules = [];
        foreach ($document->paths as $path) {
            $module = self::moduleOf($path->path);
            $modules[$module] = true;

            foreach ($path->operations as $operation) {
                $operation->setTags([$module]);
            }
        }

        // Step 2: one described tag per module, alphabetical
        $modules = array_keys($modules);
        sort($modules);

        $document->tags = array_map(fn (string $module): Tag => new Tag($module, $this->description($module)), $modules);
    }

    public static function moduleOf(string $path): string
    {
        return explode('/', ltrim($path, '/'))[0] ?: self::ROOT_MODULE;
    }

    private function description(string $module): ?string
    {
        $parts = array_filter([
            ApiModuleDescriptions::DESCRIPTIONS[$module] ?? null,
            array_key_exists($module, config('tables.definitions', []))
                ? "List: `POST /api/tables/{$module}/rows` (columns: `GET /api/tables/{$module}/columns`)."
                : null,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }
}
