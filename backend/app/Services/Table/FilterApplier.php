<?php

namespace App\Services\Table;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-type filter application for the generic SSRM engine.
 *
 * Extracted out of TableService (spec 0004) so the SSRM orchestration
 * (offset/limit/sort/pagination) and the per-type filter branches (text/
 * number/boolean/date/set/multi/combined) each stay in a single, focused
 * file. Called ONLY against real DB columns already resolved from the
 * definition's filterable whitelist — derived columns (e.g. `roles`) are
 * handled by the definition's applyDerivedFilter hook before reaching here.
 * Every value stays a bound query-builder parameter; LIKE wildcards are
 * escaped. Security invariant lives with the caller (TableService): this
 * class never sees an un-whitelisted column id.
 */
class FilterApplier
{
    /**
     * Maximum number of values honoured in a set filter. Caps the WHERE IN
     * cardinality (defence in depth) regardless of whether the column
     * declares a static options catalogue.
     */
    private const int MAX_SET_FILTER_VALUES = 500;

    /**
     * Column types whose empty string reads as "no value" to the user, so the
     * Set Filter's blank entry covers BOTH `NULL` and `''`. Numeric/date
     * columns are excluded on purpose: MySQL coerces `= ''` to 0 on a numeric
     * column, which would match real rows.
     *
     * @var array<int, string>
     */
    private const array BLANK_INCLUDES_EMPTY_STRING_TYPES = ['text', 'set', 'enum', 'badge', 'tags'];

    /**
     * Apply one column's filter payload to the query.
     *
     * `$filter['filterType']` drives the branch when present — it carries the
     * widget's own shape, e.g. `multi` on a column whose declared type is
     * `text`/`number`/`date` — falling back to the column's declared
     * `filterType` otherwise.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function apply(Builder $query, string $column, array $columnConfig, array $filter): void
    {
        $filterType = is_string($filter['filterType'] ?? null) ? $filter['filterType'] : ($columnConfig['filterType'] ?? null);

        match ($filterType) {
            'multi' => $this->applyMulti($query, $column, $columnConfig, $filter),
            'set' => $this->applySet($query, $column, $columnConfig, $filter),
            'boolean' => $this->applyBoolean($query, $column, $filter),
            'date' => $this->applyCombinable($query, $filter, fn (Builder $q, array $f, string $b): mixed => $this->applyDate($q, $column, $f, $b)),
            'number' => $this->applyCombinable($query, $filter, fn (Builder $q, array $f, string $b): mixed => $this->applyNumber($q, $column, $f, $b)),
            default => $this->applyCombinable($query, $filter, fn (Builder $q, array $f, string $b): mixed => $this->applyText($q, $column, $f, $b)),
        };
    }

    /**
     * Set filter: WHERE column IN (?, ?...). A column with a declared static
     * options catalogue (enum columns, e.g. locale) treats it as a whitelist;
     * a column with none — e.g. the Set sub-filter of a `multi` widget on a
     * free-text/number/date column — accepts any of its own scalar values (the
     * candidate list it was populated from already came from the bounded
     * /values endpoint, never unbounded client input). Either way the
     * cardinality is capped and every value stays a bound parameter.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    private function applySet(Builder $query, string $column, array $columnConfig, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values) || $values === []) {
            return;
        }

        // AG Grid's blank entry ("(Vuoti)") round-trips as a `null` inside
        // `values`: it selects the rows whose cell is empty, which no
        // `whereIn` can express — hence the OR group below.
        $matchesBlank = in_array(null, $values, true);

        $scalarValues = array_values(array_filter($values, static fn ($value): bool => is_scalar($value)));

        $options = $columnConfig['options'] ?? null;

        $clean = is_array($options) && $options !== []
            ? array_values(array_filter($scalarValues, static fn ($value): bool => in_array($value, $options, true)))
            : $scalarValues;

        $clean = array_slice($clean, 0, self::MAX_SET_FILTER_VALUES);

        if ($clean === [] && ! $matchesBlank) {
            return;
        }

        $includesEmptyString = $this->treatsEmptyStringAsBlank($columnConfig);

        $query->where(static function (Builder $group) use ($column, $clean, $matchesBlank, $includesEmptyString): void {
            if ($clean !== []) {
                $group->whereIn($column, $clean);
            }

            if ($matchesBlank) {
                $group->orWhereNull($column);

                if ($includesEmptyString) {
                    $group->orWhere($column, '=', '');
                }
            }
        });
    }

    /**
     * Whether this column's blank entry also covers the empty string, on top of
     * `NULL`. Public because TableService builds the very same blank entry when
     * it lists a column's distinct values: both sides must agree on what "empty
     * cell" means, or a selectable value would filter nothing.
     *
     * @param  array<string, mixed>  $columnConfig
     */
    public function treatsEmptyStringAsBlank(array $columnConfig): bool
    {
        // The DATA type decides, not the widget: a numeric column exposed as a
        // set filter must still compare against NULL only.
        $type = $columnConfig['type'] ?? $columnConfig['filterType'] ?? null;

        return is_string($type) && in_array($type, self::BLANK_INCLUDES_EMPTY_STRING_TYPES, true);
    }

    /**
     * Boolean filter: accepts `values:[true]`/`[false]`/`[true,false]` (set
     * shape) or a single `{filter|type}` boolean-equivalent payload. A single
     * distinct value narrows with `=`; two distinct values match either (kept
     * as a `whereIn` rather than a no-op, so the intent stays explicit in SQL).
     *
     * @param  array<string, mixed>  $filter
     */
    private function applyBoolean(Builder $query, string $column, array $filter): void
    {
        $values = $this->booleanFilterValues($filter);

        if ($values === null || $values === []) {
            return;
        }

        if (count($values) === 1) {
            $query->where($column, '=', $values[0]);

            return;
        }

        $query->whereIn($column, $values);
    }

    /**
     * Extract the distinct boolean values carried by a boolean filter payload.
     * Returns null when the payload carries no usable boolean value.
     *
     * @param  array<string, mixed>  $filter
     * @return array<int, bool>|null
     */
    private function booleanFilterValues(array $filter): ?array
    {
        $values = $filter['values'] ?? null;

        if (is_array($values)) {
            $clean = [];

            foreach ($values as $value) {
                if (is_bool($value) && ! in_array($value, $clean, true)) {
                    $clean[] = $value;
                }
            }

            return $clean === [] ? null : $clean;
        }

        $single = $filter['filter'] ?? ($filter['type'] ?? null);

        if (is_bool($single)) {
            return [$single];
        }

        if (is_string($single) && in_array($single, ['true', 'false'], true)) {
            return [$single === 'true'];
        }

        return null;
    }

    /**
     * Multi filter (agMultiColumnFilter): a Set sub-model plus a typed
     * condition sub-model, applied in AND when both are present. Each
     * sub-model carries its own `filterType` (set/text/number/date), so it is
     * simply re-dispatched through apply().
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    private function applyMulti(Builder $query, string $column, array $columnConfig, array $filter): void
    {
        $filterModels = $filter['filterModels'] ?? null;

        if (! is_array($filterModels)) {
            return;
        }

        foreach ($filterModels as $subFilter) {
            if (is_array($subFilter) && $subFilter !== []) {
                $this->apply($query, $column, $columnConfig, $subFilter);
            }
        }
    }

    /**
     * Detect a combined filter `{operator: AND|OR, conditions: [c1, c2]}` and
     * apply both sub-conditions inside a single nested `where`, so the
     * combining boolean never leaks into the AND between different columns.
     * A plain (non-combined) filter is applied directly via `$applySingle`.
     *
     * @param  array<string, mixed>  $filter
     * @param  callable(Builder<Model>, array<string, mixed>, string): mixed  $applySingle
     */
    private function applyCombinable(Builder $query, array $filter, callable $applySingle): void
    {
        $conditions = $filter['conditions'] ?? null;

        if (! is_array($conditions) || $conditions === []) {
            $applySingle($query, $filter, 'and');

            return;
        }

        $boolean = ($filter['operator'] ?? null) === 'OR' ? 'or' : 'and';

        $query->where(function (Builder $nested) use ($conditions, $boolean, $applySingle): void {
            foreach ($conditions as $condition) {
                if (is_array($condition)) {
                    $applySingle($nested, $condition, $boolean);
                }
            }
        });
    }

    /**
     * Text filter (name, email): bound LIKE/equality, value never inlined.
     *
     * @param  array<string, mixed>  $filter
     */
    private function applyText(Builder $query, string $column, array $filter, string $boolean): void
    {
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return;
        }

        $value = (string) $value;
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'contains';

        match ($type) {
            'equals' => $query->where($column, '=', $value, $boolean),
            'notEqual' => $query->where($column, '!=', $value, $boolean),
            'startsWith' => $query->where($column, 'like', $this->escapeLike($value).'%', $boolean),
            'endsWith' => $query->where($column, 'like', '%'.$this->escapeLike($value), $boolean),
            'notContains' => $query->where($column, 'not like', '%'.$this->escapeLike($value).'%', $boolean),
            default => $query->where($column, 'like', '%'.$this->escapeLike($value).'%', $boolean),
        };
    }

    /**
     * Number filter: equals/notEqual/greaterThan(OrEqual)/lessThan(OrEqual)
     * bind a single value; inRange binds both bounds via whereBetween.
     *
     * @param  array<string, mixed>  $filter
     */
    private function applyNumber(Builder $query, string $column, array $filter, string $boolean): void
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';

        if ($type === 'inRange') {
            $from = $this->numericOrNull($filter['filter'] ?? null);
            $to = $this->numericOrNull($filter['filterTo'] ?? null);

            if ($from !== null && $to !== null) {
                $query->whereBetween($column, [$from, $to], $boolean);
            }

            return;
        }

        $value = $this->numericOrNull($filter['filter'] ?? null);

        if ($value === null) {
            return;
        }

        $operator = match ($type) {
            'notEqual' => '!=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            default => '=', // 'equals'
        };

        $query->where($column, $operator, $value, $boolean);
    }

    /**
     * Coerce a filter payload value to a bound int|float, or null when it is
     * not a usable numeric value (so the filter adds no constraint).
     */
    private function numericOrNull(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * Apply a date filter (equals or inRange) on a datetime column.
     *
     * @param  array<string, mixed>  $filter
     */
    private function applyDate(Builder $query, string $column, array $filter, string $boolean): void
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';
        $from = is_string($filter['dateFrom'] ?? null) ? $filter['dateFrom'] : null;
        $to = is_string($filter['dateTo'] ?? null) ? $filter['dateTo'] : null;

        if ($type === 'inRange' && $from !== null && $to !== null) {
            $query->whereBetween($column, [$from, $to], $boolean);

            return;
        }

        if ($from === null) {
            return;
        }

        match ($type) {
            'greaterThan' => $query->where($column, '>', $from, $boolean),
            'lessThan' => $query->where($column, '<', $from, $boolean),
            'notEqual' => $query->whereDate($column, '!=', $from, $boolean),
            default => $query->whereDate($column, '=', $from, $boolean),
        };
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    public function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
