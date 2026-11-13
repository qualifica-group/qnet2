<?php

namespace App\Services\ApiDocs\Docs;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;

/**
 * Replaces the summary and description Scramble infers from the controller
 * PHPDoc (developer notes, often internal) with the integrator-facing ones of
 * `resources/api-docs/operations/{module}.php`: one file per module, keyed by
 * "METHOD /path" exactly as the document prints it. Written for a reader that
 * only has the OpenAPI document, such as another AI agent.
 */
class ApiOperationDescriptionsTransformer implements DocumentTransformer
{
    public const string DIRECTORY = 'api-docs/operations';

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $modules = [];

        foreach ($document->paths as $path) {
            $module = ApiModuleTagsTransformer::moduleOf($path->path);
            $descriptions = $modules[$module] ??= self::forModule($module);

            foreach ($path->operations as $operation) {
                $entry = $descriptions[self::key($operation->method, $path->path)] ?? null;

                if ($entry !== null) {
                    $operation->summary($entry['summary']);
                    $operation->description($entry['description']);
                }
            }
        }
    }

    public static function key(string $method, string $path): string
    {
        return strtoupper($method).' /'.ltrim($path, '/');
    }

    /**
     * @return array<string, array{summary: string, description: string}>
     */
    public static function forModule(string $module): array
    {
        $file = resource_path(self::DIRECTORY."/{$module}.php");

        return is_file($file) ? require $file : [];
    }
}
