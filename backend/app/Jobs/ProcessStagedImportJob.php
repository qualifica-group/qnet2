<?php

namespace App\Jobs;

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowResolution;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Imports\Staging\StagingErrorReporter;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Notifications\ImportCompletedNotification;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Phase C of the unified import wizard (spec 0033, AC-009): RE-READS the
 * staged `import_run_rows` — never the source file again — and, for every
 * row NOT `error`/`skipped`, calls the definition's persistRow() inside its
 * OWN DB::transaction, so one row's commit-time failure is isolated, added to
 * the errors report, and never blocks the others. Updates imported_rows/
 * error_count, moves the run to `completed`, and sends
 * ImportCompletedNotification exactly once (guarded by `notified_at`). On any
 * unhandled failure the run moves to `failed` instead of staying stuck
 * (AC-010).
 *
 * Hardening (spec 0136, D-7/D-8): `$tries = 1` — a killed worker must land
 * in failed() below, never in a silent Laravel retry. `$timeout` comes from
 * `imports.job_timeout`. Only persistable rows still missing `persisted_at`
 * are read, in `imports.batch_size` chunks (`lazyById()`), and each row's
 * `persisted_at` is set INSIDE the same transaction that commits it — a
 * re-run of this job never re-persists an already-written row. `error_count`
 * stays scoped to failures of THIS run (unchanged semantics); `imported_rows`
 * is recomputed by query across ALL executions (rows with `persisted_at` set
 * that actually wrote, same rule as the former isWritten() check).
 */
class ProcessStagedImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Staged statuses that are actually committed by persistRow() (also the commit progress total, spec 0137). */
    public const array PERSISTABLE_STATUSES = [
        ImportRowStatus::Valid,
        ImportRowStatus::Warning,
        ImportRowStatus::Duplicate,
    ];

    public int $tries = 1;

    public int $timeout;

    public function __construct(private readonly int $importRunId)
    {
        $this->timeout = (int) config('imports.job_timeout');
    }

    public function handle(ImportRegistry $registry, ImportService $importService, StagingErrorReporter $errorReporter): void
    {
        // See AnalyzeImportJob for why this is lifted (sync queue driver).
        set_time_limit(0);

        /** @var ImportRun $run */
        $run = ImportRun::query()->findOrFail($this->importRunId);

        try {
            $definition = $registry->resolve($run->resource);
            /** @var User $actor */
            $actor = User::query()->findOrFail($run->user_id);

            $failures = $this->persistStagedRows($run, $definition, $actor);

            $errorReporter->write($run, $failures);

            $run->update([
                'imported_rows' => $this->countImportedRows($run),
                'error_count' => count($failures),
                'status' => ImportStatus::Completed,
            ]);

            $this->notifyCompletion($run->fresh());
        } catch (Throwable $exception) {
            $run->update(['status' => ImportStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * See StageImportJob::failed() — a killed worker skips the catch above and
     * would leave the run polling forever in `processing`.
     */
    public function failed(?Throwable $exception): void
    {
        $run = ImportRun::query()->find($this->importRunId);

        if ($run?->status === ImportStatus::Processing) {
            $run->update(['status' => ImportStatus::Failed]);
        }
    }

    /**
     * @return array<int, array{row: ImportRunRow, message: string}>
     */
    private function persistStagedRows(ImportRun $run, ImportDefinition $definition, User $actor): array
    {
        $globalConfig = $run->global_config ?? [];
        $dedupStrategy = $run->dedup_strategy ?? ImportDedupMode::CreateOnly->value;
        $convertToOpportunity = $run->convert_to_opportunity;

        $failures = [];

        foreach ($this->pendingRows($run) as $row) {
            $message = $this->persistOneRow($definition, $actor, $row, $globalConfig, $dedupStrategy, $convertToOpportunity);

            if ($message !== null) {
                $failures[] = ['row' => $row, 'message' => $message];
            }
        }

        return $failures;
    }

    /**
     * Persistable rows not yet committed by a previous execution of this job
     * (D-8: `persisted_at` still null). Chunked via lazyById() instead of one
     * `get()` so memory stays bounded on large files — StageImportJob writes
     * rows strictly in file/row_number order with strictly increasing ids,
     * so ordering by id is equivalent, no extra `orderBy('row_number')`
     * needed.
     */
    private function pendingRows(ImportRun $run): LazyCollection
    {
        return $run->rows()
            ->whereIn('status', self::PERSISTABLE_STATUSES)
            ->whereNull('persisted_at')
            ->lazyById((int) config('imports.batch_size'));
    }

    /**
     * Rows actually written across ALL executions of this job (D-8), not
     * just this run's loop — a retry that only revisits the still-pending
     * rows must still report the full total. A `valid`/`warning` row always
     * writes; a `duplicate` row only writes when its per-row `resolution`
     * (spec 0036) is `create`/`update` — an unresolved/`skip` duplicate
     * never reaches the database and must never count as imported (spec
     * 0036 AC-005 bug fix).
     */
    private function countImportedRows(ImportRun $run): int
    {
        return $run->rows()
            ->whereNotNull('persisted_at')
            ->where(function ($query): void {
                $query->whereIn('status', [ImportRowStatus::Valid, ImportRowStatus::Warning])
                    ->orWhere(function ($query): void {
                        $query->where('status', ImportRowStatus::Duplicate)
                            ->whereIn('resolution', [ImportRowResolution::Create, ImportRowResolution::Update]);
                    });
            })
            ->count();
    }

    /**
     * Persist one staged row in its OWN transaction, isolating a commit-time
     * failure so it never blocks the other rows, and stamp `persisted_at`
     * INSIDE that same transaction (D-8): a row that throws rolls back
     * together with its would-be `persisted_at`, so it is picked up again by
     * the next execution. Returns a motivated error string on failure, null
     * on success.
     *
     * @param  array<string, mixed>  $globalConfig
     */
    private function persistOneRow(ImportDefinition $definition, User $actor, ImportRunRow $row, array $globalConfig, string $dedupStrategy, bool $convertToOpportunity): ?string
    {
        try {
            DB::transaction(function () use ($definition, $actor, $row, $globalConfig, $dedupStrategy, $convertToOpportunity): void {
                $definition->persistRow($actor, $row, $globalConfig, $dedupStrategy, $convertToOpportunity);
                $row->update(['persisted_at' => now()]);
            });

            return null;
        } catch (Throwable $exception) {
            return 'Failed to persist the record: '.$exception->getMessage();
        }
    }

    /**
     * Send the completion notification exactly once, guarded by
     * `notified_at` — a re-dispatch of this job (e.g. queue retry after a
     * partial failure elsewhere) never double-notifies the user.
     */
    private function notifyCompletion(ImportRun $run): void
    {
        if ($run->notified_at !== null) {
            return;
        }

        $run->user->notify(new ImportCompletedNotification($run));

        $run->update(['notified_at' => now()]);
    }
}
