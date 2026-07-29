<?php

namespace App\Jobs;

use App\Enums\ImportDedupMode;
use App\Enums\ImportStatus;
use App\Imports\ImportRegistry;
use App\Imports\Staging\StagedRowBuilder;
use App\Imports\Staging\StageOutcome;
use App\Imports\Staging\StagingErrorReporter;
use App\Imports\Support\SpreadsheetReader;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Phase B of the unified import wizard (spec 0033, AC-008): re-reads the
 * stored file and, for EVERY row, applies the configured `column_mapping` +
 * the definition's recognizers() + dedup resolution (via StagedRowBuilder),
 * writing ONE `import_run_rows` per file row — NO domain record is ever
 * created here. Counters are recomputed from the staged rows
 * (ImportService::recomputeCounts()) and the errors report is (re)written
 * from the staged error/skipped rows. The run moves to `reviewing`. On any
 * unhandled failure the run moves to `failed` instead of staying stuck
 * (AC-010).
 */
class StageImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $importRunId) {}

    public function handle(
        ImportRegistry $registry,
        SpreadsheetReader $reader,
        ImportService $importService,
        StagingErrorReporter $errorReporter,
    ): void {
        // See AnalyzeImportJob for why this is lifted (sync queue driver).
        set_time_limit(0);

        /** @var ImportRun $run */
        $run = ImportRun::query()->findOrFail($this->importRunId);

        try {
            $definition = $registry->resolve($run->resource);
            /** @var User $actor */
            $actor = User::query()->findOrFail($run->user_id);
            $dedupMode = ImportDedupMode::from($run->dedup_strategy ?? ImportDedupMode::CreateOnly->value);
            $builder = new StagedRowBuilder($definition, $actor, $run->column_mapping ?? [], $dedupMode, $run->global_config ?? []);

            $this->stageRows($run, $reader, $builder);

            $importService->recomputeCounts($run->fresh());
            $errorReporter->write($run->fresh());

            $run->update(['status' => ImportStatus::Reviewing]);
        } catch (Throwable $exception) {
            $run->update(['status' => ImportStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * A worker that kills the job (queue timeout, exhausted attempts, PHP
     * fatal) never reaches handle()'s catch, and a run left in `staging` is
     * polled forever by the wizard with no way out. AC-010 holds either way:
     * an unhandled failure moves the run to `failed`, it never stays stuck.
     */
    public function failed(?Throwable $exception): void
    {
        $run = ImportRun::query()->find($this->importRunId);

        if ($run?->status === ImportStatus::Staging) {
            $run->update(['status' => ImportStatus::Failed]);
        }
    }

    private function stageRows(ImportRun $run, SpreadsheetReader $reader, StagedRowBuilder $builder): void
    {
        $path = Storage::disk('local')->path($run->stored_path);

        foreach ($reader->rows($path) as $rowNumber => $rawValues) {
            $outcome = $builder->build($rowNumber, $rawValues);

            $this->persistStagedRow($run, $rowNumber, $rawValues, $outcome);
        }
    }

    /**
     * @param  array<string, string>  $rawValues
     */
    private function persistStagedRow(ImportRun $run, int $rowNumber, array $rawValues, StageOutcome $outcome): void
    {
        ImportRunRow::create([
            'import_run_id' => $run->id,
            'row_number' => $rowNumber,
            'raw_values' => $rawValues,
            'mapped_values' => $outcome->mappedValues,
            'extra_values' => $outcome->extraValues,
            'resolved' => $outcome->resolved,
            'status' => $outcome->status,
            'messages' => $outcome->messages,
            'duplicate_of_id' => $outcome->duplicateOfId,
            'duplicate_meta' => $outcome->duplicateMeta,
        ]);
    }
}
