<?php

namespace App\Console\Commands;

use App\Services\ApiDocs\OpenApiDocumentProvider;
use Illuminate\Console\Command;

/**
 * Deploy step: run `php artisan api-docs:warm` after every release. Cold
 * generation of the OpenAPI document takes tens of seconds, so without
 * this step the first Documentation request answers 202 and triggers a background
 * generation instead. The document is stored under the same key and signature
 * OpenApiDocumentProvider reads.
 */
class WarmApiDocs extends Command
{
    protected $signature = 'api-docs:warm';

    protected $description = 'Generate the API OpenAPI document and store it in the cache';

    public function handle(OpenApiDocumentProvider $documents): int
    {
        $started = microtime(true);

        $operations = collect($documents->generate()['paths'] ?? [])->sum(fn (array $path): int => count($path));

        $this->info(sprintf('API documentation ready: %d operations in %.1fs.', $operations, microtime(true) - $started));

        return self::SUCCESS;
    }
}
