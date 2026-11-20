<?php

namespace App\Services\Merge;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Counts, per module, what links to each record of a merge (spec 0394, D-8),
 * with a short sample of labelled rows for the preview.
 */
final class MergeLinkedCounter
{
    /** Samples shown per key and record (data contract). */
    public const int SAMPLE_LIMIT = 20;

    private const int LABEL_LENGTH = 80;

    /** Columns tried, in order, as the human label of a linked row. */
    private const array LABEL_COLUMNS = ['code', 'number', 'document_number', 'title', 'name', 'subject', 'description', 'reference'];

    public function __construct(private readonly RelationValueRewriter $relationValues) {}

    /**
     * @param  list<int>  $ids
     * @return array<string, array{counts: array<int, int>, items: list<array{id: int, record_id: int, label: string}>}>
     */
    public function count(MergeDefinition $definition, array $ids): array
    {
        $linked = [];

        foreach ($definition->linkedSources() as $source) {
            foreach ($source->ownerColumns as $column) {
                $this->countColumn($linked, $source, $column, $ids);
            }
        }

        foreach ($this->relationValues->find($definition->table(), $ids) as $hit) {
            $this->add($linked, 'attribute_values', $hit['record_id'], 1, [['id' => $hit['row_id'], 'record_id' => $hit['record_id'], 'label' => $hit['source']]]);
        }

        return $linked;
    }

    /**
     * @param  array<string, array{counts: array<int, int>, items: list<array{id: int, record_id: int, label: string}>}>  $linked
     * @param  list<int>  $ids
     */
    private function countColumn(array &$linked, LinkedSource $source, string $column, array $ids): void
    {
        $totals = $this->query($source, $column, $ids)->select($column.' as owner_id', DB::raw('count(*) as total'))->groupBy($column)->get();

        foreach ($totals as $total) {
            $this->add($linked, $source->key, (int) $total->owner_id, (int) $total->total, []);
        }

        if ($totals->isEmpty()) {
            return;
        }

        // Samples per record, so the preview can show what each duplicate brings whichever survivor is chosen.
        $labelColumn = $this->labelColumn($source->table);

        foreach ($totals as $total) {
            $ownerId = (int) $total->owner_id;
            $room = self::SAMPLE_LIMIT - $this->samplesOf($linked, $source->key, $ownerId);

            if ($room <= 0) {
                continue;
            }

            $rows = $this->query($source, $column, [$ownerId])
                ->select("{$source->table}.id as row_id")
                ->when($labelColumn !== null, static fn (Builder $query) => $query->addSelect("{$source->table}.{$labelColumn} as row_label"))
                ->orderBy("{$source->table}.id")
                ->limit($room)
                ->get();

            foreach ($rows as $row) {
                $label = Str::limit(trim((string) ($row->row_label ?? '')), self::LABEL_LENGTH);
                $this->add($linked, $source->key, $ownerId, 0, [[
                    'id' => (int) $row->row_id,
                    'record_id' => $ownerId,
                    'label' => $label === '' ? "#{$row->row_id}" : $label,
                ]]);
            }
        }
    }

    /**
     * @param  array<string, array{counts: array<int, int>, items: list<array{id: int, record_id: int, label: string}>}>  $linked
     */
    private function samplesOf(array $linked, string $key, int $recordId): int
    {
        return count(array_filter($linked[$key]['items'] ?? [], static fn (array $item): bool => $item['record_id'] === $recordId));
    }

    /**
     * @param  list<int>  $ids
     */
    private function query(LinkedSource $source, string $column, array $ids): Builder
    {
        $query = DB::table($source->table);

        foreach ($source->joins as [$table, $local, $foreign]) {
            $query->join($table, $local, '=', $foreign);
        }

        $query->whereIn($column, $ids);

        if ($source->typeColumn !== null) {
            $query->where($source->typeColumn, $source->typeAlias);
        }

        if (Schema::hasColumn($source->table, 'deleted_at')) {
            $query->whereNull("{$source->table}.deleted_at");
        }

        return $query;
    }

    private function labelColumn(string $table): ?string
    {
        $columns = Schema::getColumnListing($table);

        foreach (self::LABEL_COLUMNS as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{counts: array<int, int>, items: list<array{id: int, record_id: int, label: string}>}>  $linked
     * @param  list<array{id: int, record_id: int, label: string}>  $items
     */
    private function add(array &$linked, string $key, int $recordId, int $count, array $items): void
    {
        $linked[$key] ??= ['counts' => [], 'items' => []];
        $linked[$key]['counts'][$recordId] = ($linked[$key]['counts'][$recordId] ?? 0) + $count;

        foreach ($items as $item) {
            if ($this->samplesOf($linked, $key, $item['record_id']) < self::SAMPLE_LIMIT) {
                $linked[$key]['items'][] = $item;
            }
        }
    }
}
