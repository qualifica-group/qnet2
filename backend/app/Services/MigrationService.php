<?php

namespace App\Services;

use App\Enums\MigrationStatus;
use App\Jobs\RunMassMigrationJob;
use App\Jobs\RunMigrationJob;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRegistry;
use App\Models\MassMigrationRun;
use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Kicks off external-data migrations (spec 0013) and the mass import (spec 0046).
 *
 * - start(): single-source — create the MigrationRun (pending) and dispatch
 *   RunMigrationJob. Mirrors ImportService::start.
 * - runSource(): the shared "process one run" core (status transitions + import),
 *   reused by BOTH RunMigrationJob and the mass orchestrator so the import logic
 *   lives in exactly one place.
 * - startMass(): read the saved plan's enabled sources, snapshot them onto a
 *   MassMigrationRun and dispatch RunMassMigrationJob to run them in order.
 * - runMassSync(): same orchestrator on a caller-provided source list, run
 *   inline instead of queued (the template seed's legacy import).
 */
class MigrationService
{
    public function __construct(
        private readonly MigrationRegistry $registry,
        private readonly MigrationPlanService $planService,
    ) {}

    public function start(User $actor, string $source): MigrationRun
    {
        /** @var MigrationRun $run */
        $run = DB::transaction(fn (): MigrationRun => MigrationRun::create([
            'source' => $source,
            'user_id' => $actor->id,
            'status' => MigrationStatus::Pending,
            'total_rows' => 0,
            'created_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'report' => null,
        ]));

        RunMigrationJob::dispatch($run->id);

        return $run;
    }

    /**
     * Process one MigrationRun end to end: pending/queued -> processing ->
     * completed. On any unhandled failure the run moves to `failed` and the
     * exception re-throws so the caller (single job re-queues/records it, mass
     * orchestrator stops the chain) decides what to do next.
     */
    public function runSource(MigrationRun $run): void
    {
        try {
            $run->update(['status' => MigrationStatus::Processing]);

            $source = $this->registry->resolve($run->source);
            /** @var User $actor */
            $actor = User::query()->findOrFail($run->user_id);

            $source->import(new MigrationImportContext(run: $run, actor: $actor));

            $run->update(['status' => MigrationStatus::Completed]);
        } catch (Throwable $exception) {
            $run->update(['status' => MigrationStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * Start a mass import from the saved plan: snapshot the enabled sources (in
     * order) onto a MassMigrationRun (pending) and dispatch the orchestrator.
     */
    public function startMass(User $actor): MassMigrationRun
    {
        $run = $this->createMassRun($actor, $this->planService->enabledSources());

        RunMassMigrationJob::dispatch($run->id);

        return $run;
    }

    /**
     * Run a FIXED, ordered subset of sources INLINE (no queue): the clean
     * template seed's legacy import (QualificaLegacyImportSeeder). Same
     * orchestrator as "Import all" — one child MigrationRun per source, first
     * failure stops the chain — so a seeded import lands in the Migrazioni
     * history exactly like a UI-launched one.
     *
     * @param  list<string>  $sources
     */
    public function runMassSync(User $actor, array $sources): MassMigrationRun
    {
        $run = $this->createMassRun($actor, $sources);

        RunMassMigrationJob::dispatchSync($run->id);

        return $run->refresh();
    }

    /**
     * @param  list<string>  $sources
     */
    private function createMassRun(User $actor, array $sources): MassMigrationRun
    {
        /** @var MassMigrationRun $run */
        $run = DB::transaction(fn (): MassMigrationRun => MassMigrationRun::create([
            'user_id' => $actor->id,
            'sources' => $sources,
            'status' => MigrationStatus::Pending,
        ]));

        return $run;
    }
}
