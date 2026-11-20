<?php

namespace App\Services\Merge;

use Illuminate\Support\Facades\DB;

/**
 * Moves every module link from the duplicates to the survivor with bulk
 * UPDATEs (spec 0394, D-8) and drops the rows that would break a uniqueness
 * rule against a survivor row (D-9), reporting them for the snapshot (D-11).
 */
final class MergeReferenceReassigner
{
    /**
     * @param  list<int>  $mergedIds
     * @return array<int, list<array{table: string, row: array<string, mixed>}>> dropped rows by merged id
     */
    public function reassign(MergeDefinition $definition, int $survivorId, array $mergedIds): array
    {
        $references = $definition->references();
        $dropped = [];

        foreach ($references->conflicts as [$table, $column, $uniqueColumns]) {
            foreach ($this->moveOrDrop($table, $column, $uniqueColumns, $survivorId, $mergedIds) as $mergedId => $rows) {
                $dropped[$mergedId] = [...($dropped[$mergedId] ?? []), ...$rows];
            }
        }

        foreach ($references->plain as [$table, $column]) {
            DB::table($table)->whereIn($column, $mergedIds)->update([$column => $survivorId]);
        }

        foreach ($references->polymorphic as [$table, $typeColumn, $idColumn]) {
            DB::table($table)->where($typeColumn, $definition->entity())->whereIn($idColumn, $mergedIds)->update([$idColumn => $survivorId]);
        }

        return $dropped;
    }

    /**
     * Row by row, so a duplicate row that collides with the survivor's (or
     * with one already moved) is dropped instead of violating the index.
     *
     * @param  list<string>  $uniqueColumns
     * @param  list<int>  $mergedIds
     * @return array<int, list<array{table: string, row: array<string, mixed>}>>
     */
    private function moveOrDrop(string $table, string $column, array $uniqueColumns, int $survivorId, array $mergedIds): array
    {
        $dropped = [];

        foreach (DB::table($table)->whereIn($column, $mergedIds)->orderBy('id')->get() as $row) {
            $twin = DB::table($table)->where($column, $survivorId);

            foreach ($uniqueColumns as $unique) {
                $row->{$unique} === null ? $twin->whereNull($unique) : $twin->where($unique, $row->{$unique});
            }

            if ($twin->exists()) {
                $dropped[(int) $row->{$column}][] = ['table' => $table, 'row' => (array) $row];
                DB::table($table)->where('id', $row->id)->delete();

                continue;
            }

            DB::table($table)->where('id', $row->id)->update([$column => $survivorId]);
        }

        return $dropped;
    }
}
