<?php

declare(strict_types=1);

namespace Integrations\Formalab\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Services\CatalogSyncLauncher;
use Integrations\Formalab\Services\ClassCatalogSync;
use Throwable;

/**
 * Queued catalogue sync of a run opened by CatalogSyncLauncher, on the
 * Formalab queue. Never retried: a killed or timed-out sync closes its run as
 * interrupted and the next request starts a fresh one.
 */
class SyncFormalabClasses implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue((string) config('formalab.queue'));
        $this->timeout = (int) config('formalab.sync_timeout_seconds');
    }

    public function handle(ClassCatalogSync $sync): void
    {
        $run = FormalabSyncRun::query()->find($this->runId);

        // A run closed while the job waited (abandoned, then closed by a newer request) is not run again.
        if ($run?->outcome === FormalabSyncRun::OUTCOME_RUNNING) {
            $sync->handle($run);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(CatalogSyncLauncher::class)->interrupt($this->runId);
    }
}
