<?php

namespace App\Console\Commands;

use App\Services\ApiDocs\OpenApiDocumentProvider;
use Illuminate\Console\Command;

/**
 * Regenerates the OpenAPI document: deploy step after every release and nightly
 * schedule (routes/console.php). The Documentation page never generates it by
 * itself, it serves the last copy stored here.
 */
class WarmApiDocs extends Command
{
    protected $signature = 'api-docs:warm';

    protected $description = 'Regenerate the API OpenAPI document served by the Documentation page';

    public function handle(OpenApiDocumentProvider $documents): int
    {
        $started = microtime(true);

        $operations = collect($documents->generate()['paths'] ?? [])->sum(fn (array $path): int => count($path));

        $this->info(sprintf('API documentation ready: %d operations in %.1fs.', $operations, microtime(true) - $started));

        return self::SUCCESS;
    }
}
