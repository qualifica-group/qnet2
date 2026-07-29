<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Imports\ImportRegistry;
use App\Imports\Support\ColumnMapper;
use App\Imports\Support\SpreadsheetReader;
use App\Models\ImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Phase A of the unified import wizard (spec 0033, AC-007): read the stored
 * file's header + row count via SpreadsheetReader (xlsx/csv, no new
 * dependency) and propose an auto-mapping via ColumnMapper against the
 * resolved ImportDefinition's fields(). NO row is read/staged/created in this
 * phase — only `detected_columns`/`total_rows` are persisted, and
 * `column_mapping` is seeded with the auto-mapping proposal as its initial,
 * still-editable value (the wizard's GET .../{importRun} endpoint recomputes
 * a live `suggested_mapping` the same way, for diffing against whatever the
 * user has since edited — see ImportController::show, spec 0033 data_contract).
 * The run moves to `configuring`. On any unhandled failure the run moves to
 * `failed` instead of staying stuck (AC-010).
 */
class AnalyzeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $importRunId) {}

    public function handle(ImportRegistry $registry, SpreadsheetReader $reader, ColumnMapper $mapper): void
    {
        // A large file can exceed PHP's max_execution_time; under the sync
        // queue driver this job runs inside the HTTP request. Lift the
        // per-script limit (harmless under async workers).
        set_time_limit(0);

        /** @var ImportRun $run */
        $run = ImportRun::query()->findOrFail($this->importRunId);

        try {
            $definition = $registry->resolve($run->resource);

            $analysis = $reader->analyze(Storage::disk('local')->path($run->stored_path));
            $suggestion = $mapper->suggest($analysis->columns, $definition->fields());

            $run->update([
                'detected_columns' => $analysis->columns,
                'total_rows' => $analysis->rowCount,
                'column_mapping' => $suggestion->mapping,
                'status' => ImportStatus::Configuring,
            ]);
        } catch (Throwable $exception) {
            $run->update(['status' => ImportStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * See StageImportJob::failed() — a killed worker skips the catch above and
     * would leave the run polling forever in `analyzing`.
     */
    public function failed(?Throwable $exception): void
    {
        $run = ImportRun::query()->find($this->importRunId);

        if ($run?->status === ImportStatus::Analyzing) {
            $run->update(['status' => ImportStatus::Failed]);
        }
    }
}
