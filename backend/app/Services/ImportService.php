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
use App\Services\Assignment\ImportRowCompetence;
use App\Services\Assignment\ImportRunRowSelection;
use App\Services\Assignment\OperatorCompetence;
use App\Services\Import\ImportOpportunityConvertibility;
use Illuminate\Database\Eloquent\Collection;
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
        private readonly LeadOperatorDistributor $distributor,
        private readonly ImportRunRowSelection $rowSelection,
        private readonly ImportRowCompetence $rowCompetence,
        private readonly OperatorCompetence $competence,
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
     * Bulk-assign an operator and/or an operational site to a batch of a
     * run's staged rows (spec 0045 bulk increment, extended to a COMBINED
     * operator+site assignment, then to `$mode` — spec 0048) — AG Grid
     * `getServerSideSelectionState()` semantics: `$rowIds` are the rows to
     * target when `$selectAll` is false, the rows to EXCLUDE (empty = every
     * row) when true. Every id in `$rowIds` is trusted to already belong to
     * `$run` — validated by the caller's FormRequest (BulkAssignRequest)
     * BEFORE this runs, never re-checked here.
     *
     * `$mode = single` (default, AC-020 retro-compat) is the original SINGLE
     * mass UPDATE, unchanged. `$mode = balanced` (AC-021) distributes the
     * targeted rows across `$operationalSiteId`'s operators via
     * LeadOperatorDistributor (load = REAL leads already assigned per
     * operator) — same algorithm as LeadAssignmentService's real-lead
     * bulk-assign. Marks every targeted row `is_edited` too, same as a
     * single-row PATCH.
     *
     * `$productIds` (spec 0094 increment): bulk-assigns the "Prodotti di
     * interesse" override, written on BOTH branches (`single`/`balanced`).
     * `import_run_rows.product_ids` is a JSON column cast to `array` on the
     * Model, but a mass UPDATE goes through the query builder, which never
     * applies Eloquent casts — a raw PHP array bound as a query parameter
     * would break the JSON column, so it is `json_encode()`d explicitly
     * before being handed to `update()`.
     *
     * Returns an AssignmentOutcome, not a plain count (spec 0110): `balanced`
     * can now leave a row without an operator when nobody at the Sede is
     * competent for it, and the caller must be able to tell the two apart.
     *
     * @param  array<int, int>  $rowIds
     * @param  array<int, int>|null  $productIds
     */
    public function bulkAssign(ImportRun $run, bool $selectAll, array $rowIds, LeadAssignmentMode $mode, ?int $operatorId, ?int $operationalSiteId, ?array $productIds = null): AssignmentOutcome
    {
        if ($mode === LeadAssignmentMode::Balanced) {
            // BulkAssignRequest guarantees operational_site_id is present
            // whenever mode=balanced.
            return $this->bulkAssignBalanced($run, $selectAll, $rowIds, (int) $operationalSiteId, $productIds);
        }

        $attributes = [
            ...($operatorId !== null ? ['operator_id' => $operatorId] : []),
            ...($operationalSiteId !== null ? ['operational_site_id' => $operationalSiteId] : []),
            ...($productIds !== null ? ['product_ids' => json_encode($productIds)] : []),
        ];

        if ($attributes === []) {
            return new AssignmentOutcome(assigned: 0);
        }

        // `single` never checks competence (spec 0110, AC-023: user decision
        // R-1) — hence `skipped` is structurally 0 on this branch.
        $updated = $this->rowSelection->query($run, $selectAll, $rowIds)
            ->update([...$attributes, 'is_edited' => true]);

        return new AssignmentOutcome(assigned: $updated);
    }

    /**
     * The `mode=balanced` branch of bulkAssign() (br-balanced, spec 0048):
     * resolve the targeted staged row ids (same select_all/row_ids
     * semantics), distribute them across $operationalSiteId's operators, and
     * write operator_id + operational_site_id + is_edited=true per operator
     * group (one mass UPDATE per operator, not per row). 422 when the Sede
     * has zero operators (AC-012's import-side counterpart). `$productIds`,
     * when present, is written identically on every group — same
     * `json_encode()` treatment as the `single` branch (see bulkAssign()'s
     * docblock for the JSON-cast mass-update gotcha).
     *
     * Competence-aware since spec 0110 (AC-020/AC-021): the Sede is still the
     * outer filter, but each row is distributed only among the operators
     * competent for ITS OWN required categories. A row nobody is competent
     * for is reported as `skipped` and keeps its current operator — the 422
     * above stays reserved for a Sede with no operators AT ALL (AC-022), so a
     * partially-covered selection is never all-or-nothing.
     *
     * @param  array<int, int>  $rowIds
     * @param  array<int, int>|null  $productIds
     */
    private function bulkAssignBalanced(ImportRun $run, bool $selectAll, array $rowIds, int $operationalSiteId, ?array $productIds = null): AssignmentOutcome
    {
        // Step 1: the targeted rows, read once with everything the
        // requirement resolver needs (INV-1).
        $rows = $this->rowSelection->rows($run, $selectAll, $rowIds, ['id', 'row_number', 'product_ids', 'mapped_values']);

        if ($rows->isEmpty()) {
            return new AssignmentOutcome(assigned: 0);
        }

        $operatorIds = $this->distributor->operatorIdsForSite($operationalSiteId);

        if ($operatorIds === []) {
            abort(422, 'The selected Sede has no operators to distribute rows to.');
        }

        // Step 2: narrow the Sede's operators to the ones competent for each
        // individual row (spec 0110, AC-020), then distribute inside those
        // pools with the shared load map.
        $candidatesByRow = $this->competence->competentByRequirement(
            $operatorIds,
            $this->rowCompetence->requiredByRow($this->withAssignedProducts($rows, $productIds), $run->global_config ?? []),
        );

        $assignments = $this->distributor->distributeAmong($candidatesByRow, $this->distributor->currentLoads($operatorIds));

        // Step 3: one mass UPDATE per operator, plus one for the rows nobody
        // is competent for — those still receive the Sede and the products
        // (AC-021), only the operator is left untouched.
        foreach ($this->distributor->groupByOperator($assignments) as $assignedOperatorId => $ids) {
            ImportRunRow::query()->whereIn('id', $ids)->update([
                'operator_id' => $assignedOperatorId,
                ...$this->balancedRowAttributes($operationalSiteId, $productIds),
            ]);
        }

        $skippedRowIds = array_values(array_diff($rows->modelKeys(), array_keys($assignments)));

        if ($skippedRowIds !== []) {
            ImportRunRow::query()->whereIn('id', $skippedRowIds)->update($this->balancedRowAttributes($operationalSiteId, $productIds));
        }

        return new AssignmentOutcome(assigned: count($assignments), skipped: count($skippedRowIds));
    }

    /**
     * The bulk `product_ids` override, when submitted, IS the rows' effective
     * "Prodotti di interesse" the moment this call lands — so the competence
     * requirement must be read from it, not from the value it is about to
     * replace (INV-1). Applied in memory only: these rows are never saved,
     * the mass UPDATEs below do the writing.
     *
     * @param  Collection<int, ImportRunRow>  $rows
     * @param  array<int, int>|null  $productIds
     * @return Collection<int, ImportRunRow>
     */
    private function withAssignedProducts(Collection $rows, ?array $productIds): Collection
    {
        if ($productIds === null) {
            return $rows;
        }

        return $rows->each(static function (ImportRunRow $row) use ($productIds): void {
            $row->product_ids = $productIds;
        });
    }

    /**
     * The attributes every targeted row receives in `balanced` mode,
     * competent or not. `product_ids` is a JSON column written through the
     * query builder, which applies no Eloquent cast — see bulkAssign()'s
     * docblock for the json_encode() gotcha.
     *
     * @param  array<int, int>|null  $productIds
     * @return array<string, mixed>
     */
    private function balancedRowAttributes(int $operationalSiteId, ?array $productIds): array
    {
        return [
            'operational_site_id' => $operationalSiteId,
            ...($productIds !== null ? ['product_ids' => json_encode($productIds)] : []),
            'is_edited' => true,
        ];
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
