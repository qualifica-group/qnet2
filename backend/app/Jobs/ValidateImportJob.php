<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Imports\ImportPreview;
use App\Imports\ImportRegistry;
use App\Imports\ImportRowProcessor;
use App\Imports\Support\CsvReader;
use App\Models\ImportRun;
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
 * Phase 1 (dry-run, spec 0012): parse the stored file, validate EVERY row via
 * the resolved ImportDefinition (field rules + natural-key dedup, through
 * ImportRowProcessor), compute total/valid/invalid, persist the bounded
 * preview, write the FULL errors report, then move the run to
 * awaiting_confirmation. NO row is ever created in this phase. On any
 * unhandled failure the run moves to `failed` instead of staying stuck.
 */
class ValidateImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $importRunId)
    {
        $this->onQueue(config('imports.queue'));
    }

    public function handle(ImportRegistry $registry, CsvReader $reader, ImportService $importService): void
    {
        // A large import can exceed PHP's max_execution_time; under the sync
        // queue driver this job runs inside the HTTP request and would be
        // killed at 30s. Lift the per-script limit (harmless under async
        // workers, which govern their own timeout).
        set_time_limit(0);

        /** @var ImportRun $run */
        $run = ImportRun::query()->findOrFail($this->importRunId);

        try {
            $definition = $registry->resolve($run->resource);
            /** @var User $actor */
            $actor = User::query()->findOrFail($run->user_id);
            $processor = new ImportRowProcessor($definition, $actor);
            $columns = $definition->columnIds();

            $rows = $reader->read(Storage::disk('local')->path($run->stored_path), $columns);

            $valid = [];
            $invalid = [];

            foreach ($rows as $rowNumber => $values) {
                $outcome = $processor->process($rowNumber, $values);

                if ($outcome->isValid()) {
                    $valid[] = $outcome;
                } else {
                    $invalid[] = $outcome;
                }
            }

            $importService->writeErrorReport($run, $columns, $invalid);

            $run->update([
                'total_rows' => count($rows),
                'valid_rows' => count($valid),
                'invalid_rows' => count($invalid),
                'preview' => ImportPreview::build($columns, $valid, $invalid),
                'status' => ImportStatus::AwaitingConfirmation,
            ]);
        } catch (Throwable $exception) {
            $run->update(['status' => ImportStatus::Failed]);

            throw $exception;
        }
    }
}
