<?php

namespace App\Services\Merge;

use App\Models\Attribute;
use App\Models\CustomFieldDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finds and rewrites the ids stored inside "relation" values (spec 0394, D-8):
 * the `attribute_values` JSON of every table that has one and the
 * `custom_field_values` JSON of the relation custom fields. A JSON path cannot
 * be updated portably, so candidate rows are selected in SQL and rewritten in
 * PHP, in chunks; the stored type (int or numeric string) is preserved.
 */
final class RelationValueRewriter
{
    private const int CHUNK_SIZE = 200;

    private const string ATTRIBUTE_COLUMN = 'attribute_values';

    private const string CUSTOM_FIELDS_TABLE = 'custom_field_values';

    /**
     * Where `$target` ids (`registries`/`referents`) appear in relation values.
     *
     * @param  list<int>  $ids
     * @return list<array{source: string, row_id: int, record_id: int}>
     */
    public function find(string $target, array $ids): array
    {
        $hits = [];

        $this->eachRow($target, function (array $slot, object $row, array $values) use ($ids, &$hits): void {
            foreach ($slot['keys'] as $key) {
                foreach (array_intersect($this->idsIn($values[$key] ?? null), $ids) as $recordId) {
                    $hits[] = ['source' => $slot['label']($row), 'row_id' => (int) $row->id, 'record_id' => $recordId];
                }
            }
        });

        return $hits;
    }

    /**
     * Replace every merged id with its survivor.
     *
     * @param  array<int, int>  $map  merged id => survivor id
     */
    public function rewrite(string $target, array $map): void
    {
        $this->eachRow($target, function (array $slot, object $row, array $values) use ($map): void {
            $changed = false;

            foreach ($slot['keys'] as $key) {
                if (! array_key_exists($key, $values)) {
                    continue;
                }

                $mapped = $this->mapValue($values[$key], $map);

                if ($mapped !== $values[$key]) {
                    $values[$key] = $mapped;
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table($slot['table'])->where('id', $row->id)->update([$slot['column'] => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        });
    }

    /**
     * @param  callable(array{table: string, column: string, keys: list<string>, label: callable(object): string}, object, array<string, mixed>): void  $visit
     */
    private function eachRow(string $target, callable $visit): void
    {
        foreach ($this->slots($target) as $slot) {
            $query = DB::table($slot['table'])
                ->select(['id', $slot['column'], ...$slot['extra']])
                ->where(static function ($match) use ($slot): void {
                    foreach ($slot['keys'] as $key) {
                        $match->orWhereNotNull("{$slot['column']}->{$key}");
                    }
                });

            foreach ($slot['filter'] as $column => $value) {
                $query->where($column, $value);
            }

            $query->orderBy('id')->chunkById(self::CHUNK_SIZE, function ($rows) use ($slot, $visit): void {
                foreach ($rows as $row) {
                    $values = json_decode((string) $row->{$slot['column']}, true);

                    if (is_array($values)) {
                        $visit($slot, $row, $values);
                    }
                }
            });
        }
    }

    /**
     * @return list<array{table: string, column: string, keys: list<string>, extra: list<string>, filter: array<string, string>, label: callable(object): string}>
     */
    private function slots(string $target): array
    {
        $slots = [];
        $codes = Attribute::query()->whereNotNull('relation_target')->get(['code', 'relation_target'])
            ->filter(static fn (Attribute $attribute): bool => ($attribute->relation_target['entity_type'] ?? null) === $target)
            ->pluck('code')->all();

        if ($codes !== []) {
            foreach (Schema::getTables() as $table) {
                if (Schema::hasColumn($table['name'], self::ATTRIBUTE_COLUMN)) {
                    $name = $table['name'];
                    $slots[] = ['table' => $name, 'column' => self::ATTRIBUTE_COLUMN, 'keys' => $codes, 'extra' => [], 'filter' => [], 'label' => static fn (object $row): string => "{$name} #{$row->id}"];
                }
            }
        }

        $definitions = CustomFieldDefinition::query()->where('type', 'relation')->get(['entity_type', 'key', 'relation_target'])
            ->filter(static fn (CustomFieldDefinition $definition): bool => ($definition->relation_target['entity_type'] ?? null) === $target)
            ->groupBy('entity_type');

        foreach ($definitions as $entityType => $group) {
            $slots[] = [
                'table' => self::CUSTOM_FIELDS_TABLE,
                'column' => 'values',
                'keys' => $group->pluck('key')->all(),
                'extra' => ['entity_id'],
                'filter' => ['entity_type' => (string) $entityType],
                'label' => static fn (object $row): string => "{$entityType} #{$row->entity_id}",
            ];
        }

        return $slots;
    }

    /**
     * @return list<int>
     */
    private function idsIn(mixed $value): array
    {
        $scalars = is_array($value) ? $value : [$value];

        return array_values(array_map('intval', array_filter($scalars, static fn (mixed $item): bool => (is_int($item) || is_string($item)) && ctype_digit((string) $item))));
    }

    /**
     * @param  array<int, int>  $map
     */
    private function mapValue(mixed $value, array $map): mixed
    {
        if (is_array($value)) {
            return array_is_list($value)
                ? array_values(array_unique(array_map(fn (mixed $item): mixed => $this->mapScalar($item, $map), $value), SORT_REGULAR))
                : $value;
        }

        return $this->mapScalar($value, $map);
    }

    /**
     * @param  array<int, int>  $map
     */
    private function mapScalar(mixed $value, array $map): mixed
    {
        if ((! is_int($value) && ! is_string($value)) || ! ctype_digit((string) $value) || ! isset($map[(int) $value])) {
            return $value;
        }

        return is_int($value) ? $map[(int) $value] : (string) $map[(int) $value];
    }
}
