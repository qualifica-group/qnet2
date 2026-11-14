<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter and distinct values of an `attr.<code>` column of type `table`
 * (spec 0180; task 427477). The cell shows the server-computed `summary`
 * when there is one, else only the row count: the filter follows the same
 * rule, so a record matches on its summary or, without one, on the summary
 * column of ANY of its rows. A table without a summary column matches on
 * nothing, as before.
 *
 * Reads the payloads the grid sends for this column (`filterType: text` with
 * `hasFilterValues`, i.e. an `agMultiColumnFilter`): the `multi` envelope, the
 * Set sub-filter, the text conditions, flat or combined. Every value is a
 * LIKE-escaped bound parameter; `$valuesColumn`, the code and the summary
 * column come from the server-side attribute row (allow-list) and go into
 * bound JSON paths, never into the SQL text (backend.md §8).
 */
final class AttributeTableFilterApplier
{
    private const int MAX_SET_FILTER_VALUES = 500;

    private const array NEGATED_TYPES = ['notEqual', 'notContains'];

    /** Defence in depth on the keys reaching a JSON path (they are validated on write too). */
    private const string KEY_PATTERN = '/^[a-z0-9_]{1,64}$/';

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @param  array<string, mixed>  $filter
     */
    public function apply(Builder $query, string $valuesColumn, array $attributeRow, array $filter): void
    {
        $code = (string) $attributeRow['code'];
        $summaryColumn = $this->summaryColumn($attributeRow);

        // Step 1: the multi envelope ANDs its non-empty sub-filters
        if (($filter['filterType'] ?? null) === 'multi') {
            foreach ($filter['filterModels'] ?? [] as $subFilter) {
                if (is_array($subFilter)) {
                    $this->apply($query, $valuesColumn, $attributeRow, $subFilter);
                }
            }

            return;
        }

        // Step 2: the Set sub-filter, one exact pattern per value
        if (($filter['filterType'] ?? null) === 'set') {
            $this->applyPatterns($query, $valuesColumn, $code, $summaryColumn, $this->setPatterns($filter), negated: false);

            return;
        }

        // Step 3: text conditions, combined (AND/OR) or flat
        if (is_array($filter['conditions'] ?? null)) {
            $boolean = strtoupper((string) ($filter['operator'] ?? 'AND')) === 'OR' ? 'or' : 'and';
            $query->where(function (Builder $group) use ($filter, $valuesColumn, $code, $summaryColumn, $boolean): void {
                foreach ($filter['conditions'] as $condition) {
                    if (is_array($condition)) {
                        $group->where(fn (Builder $one) => $this->applyText($one, $valuesColumn, $code, $summaryColumn, $condition), boolean: $boolean);
                    }
                }
            });

            return;
        }

        $this->applyText($query, $valuesColumn, $code, $summaryColumn, $filter);
    }

    /**
     * The summary of each record or, without one, the summary column of every
     * row: the values the filter above can match, deduplicated and sorted.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, string $valuesColumn, array $attributeRow): array
    {
        $summaryColumn = $this->summaryColumn($attributeRow);

        if ($summaryColumn === null) {
            return [];
        }

        $values = [];

        $stored = (clone $query)->select("{$valuesColumn}->{$attributeRow['code']} as table_value")->pluck('table_value');

        foreach ($stored as $table) {
            array_push($values, ...$this->matchableValues($table, $summaryColumn));
        }

        $unique = array_values(array_unique($values));
        sort($unique);

        return $unique;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyText(Builder $query, string $valuesColumn, string $code, ?string $summaryColumn, array $filter): void
    {
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return;
        }

        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'contains';
        $escaped = $this->escapeLike((string) $value);
        $pattern = match ($type) {
            'equals', 'notEqual' => $escaped,
            'startsWith' => $escaped.'%',
            'endsWith' => '%'.$escaped,
            default => '%'.$escaped.'%',
        };

        $this->applyPatterns($query, $valuesColumn, $code, $summaryColumn, [$pattern], in_array($type, self::NEGATED_TYPES, true));
    }

    /**
     * (summary present AND it matches) OR (no summary AND a row matches), negated as a whole for notEqual/notContains.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $patterns
     */
    private function applyPatterns(Builder $query, string $valuesColumn, string $code, ?string $summaryColumn, array $patterns, bool $negated): void
    {
        if ($patterns === []) {
            return;
        }

        $summaryPath = "{$valuesColumn}->{$code}->summary";
        $matches = function (Builder $match) use ($summaryPath, $valuesColumn, $code, $summaryColumn, $patterns): void {
            $match->where(function (Builder $summary) use ($summaryPath, $patterns): void {
                $summary->whereNotNull($summaryPath)->where(function (Builder $any) use ($summaryPath, $patterns): void {
                    foreach ($patterns as $pattern) {
                        $any->orWhere($summaryPath, 'like', $pattern);
                    }
                });
            });

            if ($summaryColumn !== null) {
                $match->orWhere(function (Builder $rows) use ($summaryPath, $valuesColumn, $code, $summaryColumn, $patterns): void {
                    $rows->whereNull($summaryPath)->where(fn (Builder $any) => $this->whereAnyRowMatches($any, $valuesColumn, $code, $summaryColumn, $patterns));
                });
            }
        };

        $negated ? $query->whereNot($matches) : $query->where($matches);
    }

    /**
     * EXISTS a row whose `$summaryColumn` cell is LIKE one of `$patterns`.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $patterns
     */
    private function whereAnyRowMatches(Builder $query, string $valuesColumn, string $code, string $summaryColumn, array $patterns): void
    {
        $column = $query->getQuery()->getGrammar()->wrap($valuesColumn);

        if ($query->getConnection()->getDriverName() === 'sqlite') {
            $likes = implode(' or ', array_fill(0, count($patterns), "json_extract(table_row.value, ?) like ? escape '\\'"));
            $bindings = ["$.\"{$code}\".rows"];

            foreach ($patterns as $pattern) {
                array_push($bindings, "$.\"{$summaryColumn}\"", $pattern);
            }

            $query->whereRaw("exists (select 1 from json_each({$column}, ?) as table_row where {$likes})", $bindings);

            return;
        }

        // MySQL/MariaDB: JSON_SEARCH takes a LIKE pattern, its escape character and a wildcard path.
        $path = "$.\"{$code}\".rows[*].\"{$summaryColumn}\"";

        foreach ($patterns as $pattern) {
            $query->orWhereRaw("json_search({$column}, 'one', ?, ?, ?) is not null", [$pattern, '\\', $path]);
        }
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function setPatterns(array $filter): array
    {
        $values = is_array($filter['values'] ?? null) ? $filter['values'] : [];
        $scalars = array_filter($values, static fn (mixed $value): bool => is_scalar($value) && $value !== '');

        return array_map(fn (mixed $value): string => $this->escapeLike((string) $value), array_slice(array_values($scalars), 0, self::MAX_SET_FILTER_VALUES));
    }

    /**
     * @return array<int, string>
     */
    private function matchableValues(mixed $stored, string $summaryColumn): array
    {
        $table = is_string($stored) ? json_decode($stored, true) : $stored;

        if (! is_array($table)) {
            return [];
        }

        if (is_scalar($table['summary'] ?? null)) {
            return [(string) $table['summary']];
        }

        $values = [];

        foreach (is_array($table['rows'] ?? null) ? $table['rows'] : [] as $row) {
            if (is_array($row) && is_scalar($row[$summaryColumn] ?? null)) {
                $values[] = (string) $row[$summaryColumn];
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    private function summaryColumn(array $attributeRow): ?string
    {
        $column = $attributeRow['config']['summary']['column'] ?? null;

        return is_string($column) && preg_match(self::KEY_PATTERN, $column) === 1
            && preg_match(self::KEY_PATTERN, (string) $attributeRow['code']) === 1 ? $column : null;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
