<?php

namespace App\Services\Merge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Builds the MergePreview of the data contract (spec 0394): what each record
 * holds, field by field, collection by collection and module by module, plus
 * the suggested survivor (D-3).
 */
final class MergePreviewBuilder
{
    public function __construct(
        private readonly MergeFieldReader $reader,
        private readonly MergeLinkedCounter $linkedCounter,
    ) {}

    /**
     * @param  Collection<int, Model>  $records  ordered by id
     * @return array<string, mixed>
     */
    public function build(MergeDefinition $definition, Collection $records): array
    {
        return $this->buildDetailed($definition, $records)['preview'];
    }

    /**
     * The preview plus the per-record link counts (`[key => [recordId => n]]`),
     * which the snapshot keeps but the contract does not expose.
     *
     * @param  Collection<int, Model>  $records  ordered by id
     * @return array{preview: array<string, mixed>, counts: array<string, array<int, int>>}
     */
    public function buildDetailed(MergeDefinition $definition, Collection $records): array
    {
        $ids = $records->map(static fn (Model $record): int => (int) $record->getKey())->all();
        $collections = $this->collections($definition, $records);
        $linked = $this->linkedCounter->count($definition, $ids);

        return [
            'preview' => [
                'suggested_survivor_id' => $this->suggestedSurvivor($ids, $collections, $linked),
                'records' => $records->map(static fn (Model $record): array => [
                    'id' => $record->getKey(),
                    'name' => $record->getAttribute('name'),
                    'old_id' => $record->getAttribute('old_id'),
                    'created_at' => $record->getAttribute('created_at')?->toIso8601String(),
                ])->values()->all(),
                'fields' => $this->fields($definition, $records),
                'collections' => array_values(array_map(
                    static fn (string $key, array $items): array => ['key' => $key, 'items' => $items],
                    array_keys($collections),
                    $collections,
                )),
                'linked' => array_values(array_map(
                    static fn (string $key, array $entry): array => [
                        'key' => $key,
                        'count' => array_sum($entry['counts']),
                        'counts' => array_map(static fn (int $recordId, int $count): array => ['record_id' => $recordId, 'count' => $count], array_keys($entry['counts']), $entry['counts']),
                        'items' => $entry['items'],
                    ],
                    array_keys($linked),
                    $linked,
                )),
            ],
            'counts' => array_map(static fn (array $entry): array => $entry['counts'], $linked),
        ];
    }

    /**
     * What one record contributed to the preview, for its snapshot (D-11).
     *
     * @param  array<string, mixed>  $preview
     * @param  array<string, array<int, int>>  $counts
     * @return array<string, mixed>
     */
    public function sliceFor(array $preview, array $counts, int $recordId): array
    {
        $ofRecord = static fn (array $items): array => array_values(array_filter($items, static fn (array $item): bool => $item['record_id'] === $recordId));

        return [
            'fields' => array_map(
                static fn (array $field): array => ['key' => $field['key'], 'value' => collect($field['values'])->firstWhere('record_id', $recordId)['value'] ?? null],
                $preview['fields'],
            ),
            'collections' => array_map(static fn (array $collection): array => ['key' => $collection['key'], 'items' => $ofRecord($collection['items'])], $preview['collections']),
            'linked' => array_map(
                static fn (array $link): array => ['key' => $link['key'], 'count' => $counts[$link['key']][$recordId] ?? 0, 'items' => $ofRecord($link['items'])],
                $preview['linked'],
            ),
        ];
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return array<int, array<string, mixed>>
     */
    private function fields(MergeDefinition $definition, Collection $records): array
    {
        $fields = $definition->fields();
        $displays = $this->reader->displays($fields, $records);

        return array_map(function (MergeField $field) use ($records, $displays): array {
            return [
                'key' => $field->key,
                'type' => $field->type,
                'combinable' => $field->combinable,
                'values' => $records->map(function (Model $record) use ($field, $displays): array {
                    $value = $this->reader->value($field, $record);

                    return [
                        'record_id' => $record->getKey(),
                        'value' => $value,
                        'display' => $field->relationModel !== null && $value !== null ? ($displays[$field->key][$value] ?? null) : null,
                    ];
                })->values()->all(),
            ];
        }, $fields);
    }

    /**
     * Collection key => elements, each with its `match_key` (D-6).
     *
     * @param  Collection<int, Model>  $records
     * @return array<string, list<array{id: int, record_id: int, label: string, detail: string|null, match_key: string|null}>>
     */
    private function collections(MergeDefinition $definition, Collection $records): array
    {
        $result = [];

        foreach ($definition->collections() as $collection) {
            $items = $collection->items($records);

            $result[$collection->key()] = array_map(static fn (MergeItem $item): array => [
                'id' => $item->id,
                'record_id' => $item->recordId,
                'label' => $item->label,
                'detail' => $item->detail,
                'match_key' => $item->matchKey,
            ], $items);
        }

        return $result;
    }

    /**
     * The record with the most links (modules + collections), the oldest on a tie (D-3).
     *
     * @param  list<int>  $ids  ascending
     * @param  array<string, list<array{record_id: int}>>  $collections
     * @param  array<string, array{counts: array<int, int>}>  $linked
     */
    private function suggestedSurvivor(array $ids, array $collections, array $linked): int
    {
        $best = $ids[0];
        $bestScore = -1;

        foreach ($ids as $id) {
            $score = array_sum(array_map(static fn (array $entry): int => $entry['counts'][$id] ?? 0, $linked))
                + array_sum(array_map(static fn (array $items): int => count(array_filter($items, static fn (array $item): bool => $item['record_id'] === $id)), $collections));

            if ($score > $bestScore) {
                $best = $id;
                $bestScore = $score;
            }
        }

        return $best;
    }
}
