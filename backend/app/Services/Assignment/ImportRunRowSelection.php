<?php

namespace App\Services\Assignment;

use App\Models\ImportRun;
use App\Models\ImportRunRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The ONE reading of AG Grid's server-side selection state against a run's
 * staged rows: `select_all: false` — `row_ids` are the rows to TARGET;
 * `select_all: true` — they are the rows to EXCLUDE (empty meaning every row
 * in the run).
 *
 * Extracted (spec 0110) because three call sites now need the exact same
 * answer and must never disagree on WHICH rows are in play: the bulk
 * assignment's coherence check, the assignment itself, and the competence
 * requirement the operator picker filters on.
 */
final class ImportRunRowSelection
{
    /**
     * @param  array<int, int>  $rowIds
     * @param  array<int, string>  $columns  the projection the caller needs
     * @return Collection<int, ImportRunRow>
     */
    public function rows(ImportRun $run, bool $selectAll, array $rowIds, array $columns = ['*']): Collection
    {
        return $this->query($run, $selectAll, $rowIds)->get($columns);
    }

    /**
     * The targeted row ids, ascending — br-balanced step 3 requires a
     * deterministic order.
     *
     * @param  array<int, int>  $rowIds
     * @return array<int, int>
     */
    public function rowIds(ImportRun $run, bool $selectAll, array $rowIds): array
    {
        return $this->query($run, $selectAll, $rowIds)->orderBy('id')->pluck('id')->map(intval(...))->all();
    }

    /**
     * @param  array<int, int>  $rowIds
     * @return Builder<ImportRunRow>
     */
    public function query(ImportRun $run, bool $selectAll, array $rowIds): Builder
    {
        return ImportRunRow::query()
            ->where('import_run_id', $run->id)
            ->when(! $selectAll, fn (Builder $query): Builder => $query->whereIn('id', $rowIds))
            ->when($selectAll && $rowIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $rowIds));
    }
}
