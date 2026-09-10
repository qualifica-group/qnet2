<?php

namespace App\Services\Import;

use App\DataObjects\Assignment\AssignmentOutcome;
use App\Enums\LeadAssignmentMode;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Services\Assignment\AssignmentCandidates;
use App\Services\Assignment\AssignmentSiteResolver;
use App\Services\Assignment\ImportRowCompetence;
use App\Services\Assignment\ImportRunRowSelection;
use App\Services\LeadOperatorDistributor;
use Illuminate\Database\Eloquent\Collection;

/**
 * The bulk assignment of a run's staged rows (PATCH .../rows/assign),
 * extracted from ImportService (spec 0113) once the Sede stopped being a
 * single value chosen by the operator and became a per-row derivation: the
 * branch grew its own resolvers and would have pushed ImportService past the
 * file-size threshold.
 *
 * AG Grid `getServerSideSelectionState()` semantics are read by
 * ImportRunRowSelection: `$rowIds` are the rows to TARGET when `$selectAll`
 * is false, the rows to EXCLUDE (empty = every row) when true. Every id is
 * trusted to already belong to `$run` — validated by BulkAssignRequest
 * BEFORE this runs, never re-checked here.
 *
 * The Sede of every row comes from the row's own CAMPAIGN (D-1) and is
 * WRITTEN back on the row (D-6/D-7), so the Lead created at commit time
 * inherits it — LeadRowPersister reads the row override and has no campaign
 * fallback of its own. Both modes write it, always grouped: one mass UPDATE
 * per distinct Sede, never one per row.
 */
final class ImportBulkAssigner
{
    public function __construct(
        private readonly ImportRunRowSelection $rowSelection,
        private readonly ImportRowCompetence $rowCompetence,
        private readonly AssignmentSiteResolver $siteResolver,
        private readonly AssignmentCandidates $candidates,
        private readonly LeadOperatorDistributor $distributor,
    ) {}

    /**
     * `$mode = single` assigns every targeted row to `$operatorId` with no
     * competence check at all (spec 0110, R-1: user decision), so `skipped`
     * is structurally 0 on that branch. `$mode = balanced` distributes each
     * row among the operators of ITS OWN Sede who are competent for ITS OWN
     * categories, and reports the rows nobody could take as `skipped`.
     *
     * `$productIds` (spec 0094) is a purely additive bulk "Prodotti di
     * interesse" override, written on both branches.
     * `import_run_rows.product_ids` is a JSON column cast to `array` on the
     * Model, but a mass UPDATE goes through the query builder, which never
     * applies Eloquent casts — a raw PHP array bound as a query parameter
     * would break the JSON column, so it is `json_encode()`d explicitly.
     *
     * @param  array<int, int>  $rowIds
     * @param  array<int, int>|null  $productIds
     */
    public function assign(ImportRun $run, bool $selectAll, array $rowIds, LeadAssignmentMode $mode, ?int $operatorId, ?array $productIds = null): AssignmentOutcome
    {
        // Step 1: the targeted rows, read once with everything the Sede and
        // the requirement resolvers need (INV-1).
        $rows = $this->rowSelection->rows($run, $selectAll, $rowIds, ['id', 'row_number', 'product_ids', 'mapped_values']);

        if ($rows->isEmpty()) {
            return new AssignmentOutcome(assigned: 0);
        }

        return $mode === LeadAssignmentMode::Balanced
            ? $this->assignBalanced($run, $rows, $productIds)
            : $this->assignSingle($run, $rows, $operatorId, $productIds);
    }

    /**
     * One operator for the whole selection. A call carrying only
     * `$productIds` assigns nobody: it touches neither the operator nor the
     * Sede (AC-017), it is a products-only edit.
     *
     * @param  Collection<int, ImportRunRow>  $rows
     * @param  array<int, int>|null  $productIds
     */
    private function assignSingle(ImportRun $run, Collection $rows, ?int $operatorId, ?array $productIds): AssignmentOutcome
    {
        $attributes = $this->sharedRowAttributes($productIds);

        if ($operatorId === null) {
            if ($productIds === null) {
                return new AssignmentOutcome(assigned: 0);
            }

            return new AssignmentOutcome(
                assigned: ImportRunRow::query()->whereIn('id', $rows->modelKeys())->update($attributes),
            );
        }

        $updated = ImportRunRow::query()
            ->whereIn('id', $rows->modelKeys())
            ->update(['operator_id' => $operatorId, ...$attributes]);

        // D-7: a row assigned by hand carries its campaign's Sede too, or the
        // Lead born at commit time would have none.
        $this->writeSites($this->siteByRow($rows, $run->global_config ?? []));

        return new AssignmentOutcome(assigned: $updated);
    }

    /**
     * "Smistamento equo" (br-balanced): each row is distributed among the
     * operators of its own Sede competent for its own categories, with ONE
     * shared load map for the entire batch (AC-011) — two Sedi with disjoint
     * pools must not rebalance in isolation. A row with no candidate (no
     * Sede, or nobody competent at that Sede) keeps the operator it had, is
     * counted as `skipped` and still receives its Sede and products
     * (AC-012): a partially covered selection is never all-or-nothing.
     *
     * @param  Collection<int, ImportRunRow>  $rows
     * @param  array<int, int>|null  $productIds
     */
    private function assignBalanced(ImportRun $run, Collection $rows, ?array $productIds): AssignmentOutcome
    {
        $globalConfig = $run->global_config ?? [];

        // Step 1: the Sede of each row's campaign and the categories that row
        // demands, composed into its candidate pool.
        $siteByRow = $this->siteByRow($rows, $globalConfig);

        $candidatesByRow = $this->candidates->byRecord(
            $siteByRow,
            $this->rowCompetence->requiredByRow($this->withAssignedProducts($rows, $productIds), $globalConfig),
        );

        // Step 2: greedy distribution over one load map for the whole batch.
        $assignments = $this->distributor->distributeAmong(
            $candidatesByRow,
            $this->distributor->currentLoads($this->unionOfCandidates($candidatesByRow)),
        );

        // Step 3: one mass UPDATE per operator, one for the rows nobody could
        // take, one per distinct Sede.
        $attributes = $this->sharedRowAttributes($productIds);

        foreach ($this->distributor->groupByOperator($assignments) as $assignedOperatorId => $ids) {
            ImportRunRow::query()->whereIn('id', $ids)->update(['operator_id' => $assignedOperatorId, ...$attributes]);
        }

        $skippedRowIds = array_values(array_diff($rows->modelKeys(), array_keys($assignments)));

        if ($skippedRowIds !== []) {
            ImportRunRow::query()->whereIn('id', $skippedRowIds)->update($attributes);
        }

        $this->writeSites($siteByRow);

        return new AssignmentOutcome(assigned: count($assignments), skipped: count($skippedRowIds));
    }

    /**
     * row id => the Sede of the row's campaign, every targeted row present:
     * an id the resolver could not answer for is read as "no Sede", the same
     * as a campaign carrying none (AC-003). This map is also what defines
     * the record set AssignmentCandidates::byRecord() iterates on.
     *
     * @param  Collection<int, ImportRunRow>  $rows
     * @param  array<string, mixed>  $globalConfig
     * @return array<int, int|null>
     */
    private function siteByRow(Collection $rows, array $globalConfig): array
    {
        $resolved = $this->siteResolver->forImportRows($rows, $globalConfig);

        $siteByRow = [];

        foreach ($rows as $row) {
            $siteByRow[(int) $row->id] = $resolved[(int) $row->id] ?? null;
        }

        return $siteByRow;
    }

    /**
     * The Sede write of both modes (D-6/D-7): one mass UPDATE per DISTINCT
     * Sede, whatever the number of rows. Rows whose Sede resolved to null
     * receive no write at all — their existing override is left alone rather
     * than being cleared.
     *
     * @param  array<int, int|null>  $siteByRow
     */
    private function writeSites(array $siteByRow): void
    {
        $rowIdsBySite = [];

        foreach ($siteByRow as $rowId => $siteId) {
            if ($siteId !== null) {
                $rowIdsBySite[$siteId][] = $rowId;
            }
        }

        foreach ($rowIdsBySite as $siteId => $ids) {
            ImportRunRow::query()->whereIn('id', $ids)->update(['operational_site_id' => $siteId]);
        }
    }

    /**
     * What every targeted row receives regardless of the operator it ends up
     * with — see assign()'s docblock for the json_encode() gotcha.
     *
     * @param  array<int, int>|null  $productIds
     * @return array<string, mixed>
     */
    private function sharedRowAttributes(?array $productIds): array
    {
        return [
            ...($productIds !== null ? ['product_ids' => json_encode($productIds)] : []),
            'is_edited' => true,
        ];
    }

    /**
     * The bulk `product_ids` override, when submitted, IS the rows' effective
     * "Prodotti di interesse" the moment this call lands — so the competence
     * requirement must be read from it, not from the value it is about to
     * replace (INV-1). Applied in memory only: these rows are never saved,
     * the mass UPDATEs do the writing.
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
     * Every operator appearing in at least one pool: the only ids whose
     * current load is worth querying.
     *
     * @param  array<int, array<int, int>>  $candidatesByRow
     * @return array<int, int>
     */
    private function unionOfCandidates(array $candidatesByRow): array
    {
        return array_values(array_unique(array_merge(...array_values($candidatesByRow) ?: [[]])));
    }
}
