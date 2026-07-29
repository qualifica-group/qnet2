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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
 */
class ProcessStagedImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Staged statuses that are actually committed by persistRow(). */
    private const array PERSISTABLE_STATUSES = [
        ImportRowStatus::Valid,
        ImportRowStatus::Warning,
        ImportRowStatus::Duplicate,
    ];

    public function __construct(private readonly int $importRunId) {}

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

            [$imported, $failures] = $this->persistStagedRows($run, $definition, $actor);

            $errorReporter->write($run, $failures);

            $run->update([
                'imported_rows' => $imported,
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
     * @return array{0: int, 1: array<int, array{row: ImportRunRow, message: string}>}
     */
    private function persistStagedRows(ImportRun $run, ImportDefinition $definition, User $actor): array
    {
        $globalConfig = $run->global_config ?? [];
        $dedupStrategy = $run->dedup_strategy ?? ImportDedupMode::CreateOnly->value;
        $convertToOpportunity = $run->convert_to_opportunity;

        /** @var Collection<int, ImportRunRow> $rows */
        $rows = $run->rows()
            ->whereIn('status', self::PERSISTABLE_STATUSES)
            ->orderBy('row_number')
            ->get();

        $imported = 0;
        $failures = [];

        foreach ($rows as $row) {
            $message = $this->persistOneRow($definition, $actor, $row, $globalConfig, $dedupStrategy, $convertToOpportunity);

            if ($message !== null) {
                $failures[] = ['row' => $row, 'message' => $message];

                continue;
            }

            if ($this->isWritten($row)) {
                $imported++;
            }
        }

        return [$imported, $failures];
    }

    /**
     * A `duplicate` row only actually writes when its per-row `resolution`
     * (spec 0036) is `create`/`update`; every other persistable row (valid,
     * warning) is always written by persistRow(). An unresolved/`skip`
     * duplicate never reaches the database, so it must never count as
     * imported (spec 0036 AC-005 bug fix).
     */
    private function isWritten(ImportRunRow $row): bool
    {
        if ($row->status !== ImportRowStatus::Duplicate) {
            return true;
        }

        return in_array($row->resolution, [ImportRowResolution::Create, ImportRowResolution::Update], true);
    }

    /**
     * Persist one staged row in its OWN transaction, isolating a commit-time
     * failure so it never blocks the other rows. Returns a motivated error
     * string on failure, null on success.
     *
     * @param  array<string, mixed>  $globalConfig
     */
    private function persistOneRow(ImportDefinition $definition, User $actor, ImportRunRow $row, array $globalConfig, string $dedupStrategy, bool $convertToOpportunity): ?string
    {
        try {
            DB::transaction(function () use ($definition, $actor, $row, $globalConfig, $dedupStrategy, $convertToOpportunity): void {
                $definition->persistRow($actor, $row, $globalConfig, $dedupStrategy, $convertToOpportunity);
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
