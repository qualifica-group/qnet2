<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Imports\ImportRowProcessor;
use App\Imports\RowOutcome;
use App\Imports\Support\CsvReader;
use App\Models\ImportRun;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Phase 2 (commit, spec 0012): re-read the stored file, re-validate every row
 * (identical rules to phase 1, via the same ImportRowProcessor) and create
 * ONLY the valid rows through the definition's createRow() — CREATE ONLY, no
 * update/upsert. Each row is created in its OWN DB::transaction, so a single
 * row's commit-time failure (e.g. a constraint violation) is isolated,
 * appended to the errors report, and never blocks the other valid rows.
 * Updates imported_rows and moves the run to `completed`; on any unhandled
 * failure the run moves to `failed` instead of staying stuck.
 */
class ProcessImportJob implements ShouldQueue
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

            [$rejected, $imported] = $this->createValidRows($rows, $processor, $definition, $actor);

            $importService->writeErrorReport($run, $columns, $rejected);

            $run->update([
                'imported_rows' => $imported,
                'status' => ImportStatus::Completed,
            ]);
        } catch (Throwable $exception) {
            $run->update(['status' => ImportStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{0: array<int, RowOutcome>, 1: int}
     */
    private function createValidRows(
        array $rows,
        ImportRowProcessor $processor,
        ImportDefinition $definition,
        User $actor,
    ): array {
        $rejected = [];
        $imported = 0;

        foreach ($rows as $rowNumber => $values) {
            $outcome = $processor->process($rowNumber, $values);

            if (! $outcome->isValid()) {
                $rejected[] = $outcome;

                continue;
            }

            $failure = $this->createRow($definition, $actor, $values);

            if ($failure !== null) {
                $rejected[] = new RowOutcome($rowNumber, $values, [$failure]);

                continue;
            }

            $imported++;
        }

        return [$rejected, $imported];
    }

    /**
     * Create one row in its own transaction, isolating a commit-time failure
     * so it never blocks the other valid rows. Returns a motivated error
     * string on failure, null on success.
     *
     * @param  array<string, string>  $values
     */
    private function createRow(ImportDefinition $definition, User $actor, array $values): ?string
    {
        try {
            DB::transaction(fn () => $definition->createRow($actor, $values));

            return null;
        } catch (Throwable $exception) {
            return 'Failed to create the record: '.$exception->getMessage();
        }
    }
}
