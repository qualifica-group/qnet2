<?php

namespace App\Jobs;

use App\Services\ApiDocs\OpenApiDocumentProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Background generation of the OpenAPI document (spec 0210): a cold Scramble run
 * takes tens of seconds, so it never happens inside the Documentation request.
 */
class GenerateApiDocsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const string LOCK_KEY = 'api-docs:generating';

    private const int LOCK_SECONDS = 600;

    public int $uniqueFor = 180;

    public function handle(OpenApiDocumentProvider $documents): void
    {
        // Under the sync driver this runs in the PHP-FPM process, capped by
        // max_execution_time; harmless with queue workers.
        set_time_limit(0);

        // The lock guards against a second generation started while one is running
        // (e.g. after the unique window expired): losers simply do nothing.
        Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS)->get(fn () => $documents->generate());
    }
}
