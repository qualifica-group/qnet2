<?php

namespace App\Jobs;

use App\Enums\MigrationStatus;
use App\Models\MigrationRun;
use App\Services\MigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Phase 2 (background import, spec 0013): process one MigrationRun's source,
 * paging through the ENTIRE external source (idempotent per `old_id`, isolated
 * per-row transactions — see AbstractMigrationSource::import). The status
 * transitions and import call live in MigrationService::runSource, shared with
 * the mass orchestrator (spec 0046); on an unhandled failure runSource moves the
 * run to `failed` and re-throws so this job is recorded as failed.
 *
 * Hardening (same as StageImportJob): `$tries = 1` so a killed worker lands in
 * failed() below, never in a silent retry; `$timeout` comes from
 * `migrations.job_timeout`, not the worker's `--timeout`.
 */
class RunMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(private readonly int $migrationRunId)
    {
        $this->timeout = (int) config('migrations.job_timeout');
    }

    public function handle(MigrationService $service): void
    {
        // A large migration can exceed PHP's max_execution_time; under the sync
        // queue driver this job runs inside the HTTP request and would be killed
        // at 30s. Lift the per-script limit (harmless under async workers, which
        // govern their own timeout).
        set_time_limit(0);

        /** @var MigrationRun $run */
        $run = MigrationRun::query()->findOrFail($this->migrationRunId);

        $service->runSource($run);
    }

    /**
     * A worker that kills the job (queue timeout, PHP fatal) never reaches
     * runSource()'s catch: without this the run stays `processing` forever and
     * its source shows as running even when every row was imported.
     */
    public function failed(?Throwable $exception): void
    {
        MigrationRun::query()
            ->whereKey($this->migrationRunId)
            ->whereIn('status', [MigrationStatus::Pending, MigrationStatus::Processing])
            ->update(['status' => MigrationStatus::Failed]);
    }
}
