<?php

namespace App\Services;

use App\DataObjects\Assignment\AssignmentOutcome;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Enums\LeadAssignmentMode;
use App\Exceptions\Import\ImportConversionNotReadyException;
use App\Imports\ImportDefinition;
use App\Imports\RowOutcome;
use App\Jobs\AnalyzeImportJob;
use App\Jobs\ProcessImportJob;
use App\Jobs\ProcessStagedImportJob;
use App\Jobs\StageImportJob;
use App\Jobs\ValidateImportJob;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Services\Import\ImportBulkAssigner;
use App\Services\Import\ImportOpportunityConvertibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Business logic for the generic import engine. Legacy two-phase flow (spec
 * 0012): create the ImportRun (start(), phase 1 dispatch), validate the
 * confirm transition (confirm(), phase 2 dispatch), and write the
 * downloadable errors CSV report — UNTOUCHED by the unified wizard flow
 * below. Unified wizard flow (spec 0033, additive): startAnalyze()/
 * configure()/confirmStaged() drive the analyze -> configure -> stage ->
 * review -> confirm state machine, each dispatching its own job; the two
 * flows never share a run (status alone tells them apart). The controller
 * stays thin; this Service is the single authority — mirrors
 * AttachmentService's disk-write conventions (private disk, uuid path, never
 * the client's filename).
 */
class ImportService
{
    private const string DISK = 'local';

    private const string DIRECTORY = 'imports';

    public function __construct(
        private readonly ImportOpportunityConvertibility $convertibility,
        private readonly ImportBulkAssigner $bulkAssigner,
    ) {}

    /**
     * Store the uploaded file, create the ImportRun (status=validating) and
     * dispatch the dry-run validation job.
     */
    public function start(User $actor, ImportDefinition $definition, UploadedFile $file): ImportRun
    {
        $storedPath = $this->storeUpload($file);

        // The 3 counters are passed explicitly (not left to the DB column
        // default) so the IN-MEMORY model returned here already reflects them
        // — the caller (ImportController::upload) serializes this same
        // instance via ImportRunResource without an extra round-trip.
        /** @var ImportRun $run */
        $run = DB::transaction(fn (): ImportRun => ImportRun::create([
            'resource' => $definition->resource(),
            'user_id' => $actor->id,
            'status' => ImportStatus::Validating,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
        ]));

        ValidateImportJob::dispatch($run->id);

        return $run;
    }

    /**
     * Move an awaiting_confirmation run to processing and dispatch the commit
     * job. Any other current status is rejected with a 422 (via abort(),
     * mapped by BaseApiController::handleControllerException — same
     * convention as RoleService/RoleAssignmentGuard).
     */
    public function confirm(ImportRun $run): ImportRun
    {
        if ($run->status !== ImportStatus::AwaitingConfirmation) {
            abort(422, 'The import cannot be confirmed in its current status.');
        }

        $run->update(['status' => ImportStatus::Processing]);

        ProcessImportJob::dispatch($run->id);

        return $run->fresh();
    }

    /**
     * Store the uploaded file, create the ImportRun (status=analyzing) and
     * dispatch the wizard's header/auto-mapping analysis job (spec 0033,
     * AC-007). Separate entry point from start(): the two-phase legacy flow
     * and the unified wizard flow never share a run.
     */
    public function startAnalyze(User $actor, ImportDefinition $definition, UploadedFile $file): ImportRun
    {
        $storedPath = $this->storeUpload($file);

        /** @var ImportRun $run */
        $run = DB::transaction(fn (): ImportRun => ImportRun::create([
            'resource' => $definition->resource(),
            'user_id' => $actor->id,
            'status' => ImportStatus::Analyzing,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
        ]));

        AnalyzeImportJob::dispatch($run->id);

        return $run;
    }

    /**
     * Persist the wizard's configuration step (column mapping, global config,
     * dedup strategy — already validated by the caller's FormRequest against
     * the definition) and dispatch staging. Valid only from `configuring`
     * (AC-008); any other status is a 422, mirroring confirm()'s guard.
     *
     * @param  array<string, string>  $columnMapping
     * @param  array<string, mixed>  $globalConfig
     */
    public function configure(ImportRun $run, array $columnMapping, array $globalConfig, string $dedupStrategy): ImportRun
    {
        if ($run->status !== ImportStatus::Configuring) {
            abort(422, 'The import cannot be configured in its current status.');
        }

        $run->update([
            'column_mapping' => $columnMapping,
            'global_config' => $globalConfig,
            'dedup_strategy' => $dedupStrategy,
            'status' => ImportStatus::Staging,
        ]);

        StageImportJob::dispatch($run->id);

        return $run->fresh();
    }

    /**
     * Move a reviewing run to processing and dispatch the commit job that
     * reads FROM the staged `import_run_rows` (AC-009) — never the source
     * file again. Valid only from `reviewing`; any other status is a 422.
     *
     * `$convertToOpportunity` (spec 0045): when true, the run must be READY
     * (ImportOpportunityConvertibility) — operational site set, campaign
     * derives a product line, every creatable row has an effective operator
     * — or this throws ImportConversionNotReadyException (caught by
     * ImportController::confirm() for the frozen `convert_blockers` 422
     * body) instead of ever dispatching the commit job. The flag (true or
     * false) is always persisted on the run so ProcessStagedImportJob ->
     * LeadsImportDefinition::persistRow() reads the operator's actual choice.
     */
    public function confirmStaged(ImportRun $run, bool $convertToOpportunity = false): ImportRun
    {
        if ($run->status !== ImportStatus::Reviewing) {
            abort(422, 'The import cannot be confirmed in its current status.');
        }

        if ($convertToOpportunity) {
            $readiness = $this->convertibility->assess($run);

            if (! $readiness->isReady()) {
                throw new ImportConversionNotReadyException($readiness);
            }
        }

        $run->update([
            'convert_to_opportunity' => $convertToOpportunity,
            'status' => ImportStatus::Processing,
        ]);

        ProcessStagedImportJob::dispatch($run->id);

        return $run->fresh();
    }

    /**
     * Recompute the run's row counters (valid/warning/invalid=error/duplicate/
     * modified) and total from its CURRENT `import_run_rows` set. Called by
     * StageImportJob after writing every row, and by the wizard's inline-edit
     * endpoint (PATCH .../rows/{row}) after a single row is re-validated — so
     * the counters shown to the user are always derived from the database,
     * never accumulated by hand.
     */
    public function recomputeCounts(ImportRun $run): void
    {
        $statusCounts = ImportRunRow::query()
            ->where('import_run_id', $run->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $run->update([
            'total_rows' => ImportRunRow::query()->where('import_run_id', $run->id)->count(),
            'valid_rows' => (int) ($statusCounts[ImportRowStatus::Valid->value] ?? 0),
            'warning_rows' => (int) ($statusCounts[ImportRowStatus::Warning->value] ?? 0),
            'invalid_rows' => (int) ($statusCounts[ImportRowStatus::Error->value] ?? 0),
            'duplicate_rows' => (int) ($statusCounts[ImportRowStatus::Duplicate->value] ?? 0),
            'modified_rows' => ImportRunRow::query()->where('import_run_id', $run->id)->where('is_edited', true)->count(),
        ]);
    }

    /**
     * Bulk-assign an operator and/or the "Prodotti di interesse" to a batch
     * of a run's staged rows (spec 0045 bulk increment, extended to `$mode`
     * by spec 0048, to the competence by 0110, to the derived Sede by 0113).
     * The whole branch lives in ImportBulkAssigner: the Sede is no longer a
     * value the operator picks but a per-row derivation with its own
     * resolvers, too much machinery to keep inside this class.
     *
     * Returns an AssignmentOutcome, not a plain count (spec 0110):
     * `balanced` can leave a row without an operator when nobody at its Sede
     * is competent for it, and the caller must tell the two apart.
     *
     * @param  array<int, int>  $rowIds
     * @param  array<int, int>|null  $productIds
     */
    public function bulkAssign(ImportRun $run, bool $selectAll, array $rowIds, LeadAssignmentMode $mode, ?int $operatorId, ?array $productIds = null): AssignmentOutcome
    {
        return $this->bulkAssigner->assign($run, $selectAll, $rowIds, $mode, $operatorId, $productIds);
    }

    /**
     * Write the errors CSV report for the given rejected rows (the FULL set,
     * not just the preview sample) and persist its path on the run. Header =
     * the definition's template columns + row_number + errors (spec 0012
     * data_contract — GET .../errors).
     *
     * @param  array<int, string>  $columns
     * @param  array<int, RowOutcome>  $rejectedRows
     */
    public function writeErrorReport(ImportRun $run, array $columns, array $rejectedRows): void
    {
        $handle = fopen('php://temp', 'w+');

        fputcsv($handle, [...$columns, 'row_number', 'errors']);

        foreach ($rejectedRows as $outcome) {
            fputcsv($handle, [
                ...array_map(static fn (string $column): string => $outcome->values[$column] ?? '', $columns),
                $outcome->rowNumber,
                implode('; ', $outcome->errors),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $path = self::DIRECTORY.'/'.Str::uuid().'-errors.csv';
        Storage::disk(self::DISK)->put($path, $csv);

        $run->update(['error_report_path' => $path]);
    }

    /**
     * Store the uploaded file on the private `local` disk. The stored object
     * name is a random UUID (the client's original name is never used as a
     * path, preventing traversal and collisions); the original name is kept
     * only as ImportRun::original_filename metadata.
     */
    private function storeUpload(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $storedName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');

        $path = Storage::disk(self::DISK)->putFileAs(self::DIRECTORY, $file, $storedName);

        if ($path === false) {
            abort(500, 'Failed to store the uploaded file.');
        }

        return $path;
    }
}
