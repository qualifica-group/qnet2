<?php

namespace App\Services\Table;

use App\Enums\AdvancedFilterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-type value validation + query application for advanced filters (spec
 * 0032). Sibling of FilterApplier (spec 0004, AG Grid column filters) for the
 * second-level, backend-driven advanced-filter panel.
 *
 * `AbstractTableDefinition::applyAdvancedFilter()` delegates here for every
 * DIRECT target: a real DB column for the scalar/range/list types, or a plain
 * Eloquent relation method name for `type: relation`/`type: async_search`
 * (matched generically via `whereHas()` on the related model's own primary
 * key — no domain knowledge required). `async_search` is ID-based exactly
 * like `relation` (the frontend's AsyncPaginatedSelect submits an id or ids,
 * never free text): the two differ only in which widget renders them, never
 * in the value shape or how they are applied. A concrete definition overrides
 * the hook only for genuinely derived logic a relation name + id-match cannot
 * express (e.g. searching a related model by a non-id field) — see
 * `TableDefinition::applyAdvancedFilter()`.
 *
 * Every value stays a bound query-builder parameter; LIKE wildcards are
 * escaped (delegated to FilterApplier, DRY with the column-filter engine);
 * never `whereRaw`/interpolation.
 */
class AdvancedFilterApplier
{
    /** Maximum values honoured in a multi-value filter (whereIn cardinality cap). */
    private const int MAX_VALUES = 500;

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * Validate an `advancedFilters` payload against a domain's catalog: every
     * key must be a declared filter name (allow-list) and its value
     * structurally valid for the descriptor's type. Shared by every FormRequest
     * that accepts such a payload (rows / filter-state / filter-views), so the
     * exact same allow-list and shape rules apply everywhere a client can
     * submit one.
     *
     * @param  array<string, array<string, mixed>>  $catalog  keyed by descriptor `name`
     * @param  array<string, mixed>  $advancedFilters
     * @return array<string, string> invalid entries as `name => error message`
     */
    public function validate(array $catalog, array $advancedFilters): array
    {
        $errors = [];

        foreach ($advancedFilters as $name => $value) {
            if (! is_string($name) || ! array_key_exists($name, $catalog)) {
                $errors[(string) $name] = "Advanced filtering is not allowed on [{$name}].";

                continue;
            }

            $descriptor = $catalog[$name];
            $type = $descriptor['type'] ?? null;

            if (! $type instanceof AdvancedFilterType || ! $this->isValidValue($type, $value, $descriptor)) {
                $errors[$name] = "Invalid value for advanced filter [{$name}].";
            }
        }

        return $errors;
    }

    /**
     * Apply one advanced filter's already-validated value to the query, against
     * `$target` (a real DB column for every type except `relation`/
     * `async_search`, where it is the Eloquent relation method name).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function apply(Builder $query, AdvancedFilterType $type, string $target, mixed $value, array $descriptor): void
    {
        $operator = is_string($descriptor['operator'] ?? null) ? $descriptor['operator'] : null;

        match ($type) {
            AdvancedFilterType::Text, AdvancedFilterType::Textarea => $this->applyLike($query, $target, $value, $operator ?? 'contains'),
            AdvancedFilterType::Number => $this->applyNumber($query, $target, $value, $operator ?? 'equals'),
            AdvancedFilterType::NumberRange => $this->applyRange($query, $target, $value, static fn (mixed $bound): int|float|null => is_numeric($bound) ? $bound + 0 : null),
            AdvancedFilterType::Date, AdvancedFilterType::Datetime => $this->applyDate($query, $target, $value, $operator ?? 'equals'),
            AdvancedFilterType::DateRange => $this->applyRange($query, $target, $value, static fn (mixed $bound): ?string => is_string($bound) && $bound !== '' ? $bound : null),
            AdvancedFilterType::Enum => ($descriptor['multiple'] ?? false) === true
                ? $this->applyWhereIn($query, $target, $value)
                : $this->applyEquals($query, $target, $value),
            AdvancedFilterType::Select, AdvancedFilterType::Radio, AdvancedFilterType::Autocomplete => $this->applyEquals($query, $target, $value),
            AdvancedFilterType::Checkbox, AdvancedFilterType::Switch => $this->applyEquals($query, $target, (bool) $value),
            AdvancedFilterType::Multiselect, AdvancedFilterType::AutocompleteMulti => $this->applyWhereIn($query, $target, $value),
            AdvancedFilterType::Relation, AdvancedFilterType::AsyncSearch => $this->applyRelation($query, $target, $value, ($descriptor['multiple'] ?? false) === true),
        };
    }

    /**
     * Structural value-shape validation for one type — no query touched.
     *
     * @param  array<string, mixed>  $descriptor
     */
    private function isValidValue(AdvancedFilterType $type, mixed $value, array $descriptor): bool
    {
        return match ($type) {
            AdvancedFilterType::Text, AdvancedFilterType::Textarea => is_string($value) && $value !== '',
            AdvancedFilterType::Number => is_numeric($value),
            AdvancedFilterType::NumberRange => $this->isValidRange($value, 'is_numeric'),
            AdvancedFilterType::Date, AdvancedFilterType::Datetime => is_string($value) && $value !== '',
            AdvancedFilterType::DateRange => $this->isValidRange($value, static fn (mixed $bound): bool => is_string($bound) && $bound !== ''),
            // An enum with `multiple` takes a list of its values (spec 0153, D-1), like a relation.
            AdvancedFilterType::Enum => ($descriptor['multiple'] ?? false) === true ? $this->isValidScalarList($value) : is_scalar($value),
            AdvancedFilterType::Select, AdvancedFilterType::Radio, AdvancedFilterType::Autocomplete => is_scalar($value),
            AdvancedFilterType::Checkbox, AdvancedFilterType::Switch => is_bool($value),
            AdvancedFilterType::Multiselect, AdvancedFilterType::AutocompleteMulti => $this->isValidScalarList($value),
            AdvancedFilterType::Relation, AdvancedFilterType::AsyncSearch => ($descriptor['multiple'] ?? false) === true ? $this->isValidScalarList($value) : is_scalar($value),
        };
    }

    /**
     * A `{from?, to?}` range value: at least one bound present, every present
     * bound passing `$isValidBound`.
     */
    private function isValidRange(mixed $value, callable $isValidBound): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        if ($from === null && $to === null) {
            return false;
        }

        return ($from === null || $isValidBound($from)) && ($to === null || $isValidBound($to));
    }

    /**
     * A non-empty array of scalar values (multiselect/autocomplete_multi/
     * relation(multi)).
     */
    private function isValidScalarList(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_scalar($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Text/textarea: bound LIKE (or equals), value never inlined.
     */
    private function applyLike(Builder $query, string $column, mixed $value, string $operator): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $escaped = $this->filterApplier->escapeLike($value);

        match ($operator) {
            'equals' => $query->where($column, '=', $value),
            'startsWith' => $query->where($column, 'like', $escaped.'%'),
            'endsWith' => $query->where($column, 'like', '%'.$escaped),
            'notContains' => $query->where($column, 'not like', '%'.$escaped.'%'),
            default => $query->where($column, 'like', '%'.$escaped.'%'), // 'contains'
        };
    }

    /**
     * Number: single bound comparison (equals by default).
     */
    private function applyNumber(Builder $query, string $column, mixed $value, string $operator): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $bound = $value + 0;

        $sqlOperator = match ($operator) {
            'notEqual' => '!=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            default => '=', // 'equals'
        };

        $query->where($column, $sqlOperator, $bound);
    }

    /**
     * Date/datetime: single bound comparison against the date part (equals by
     * default).
     */
    private function applyDate(Builder $query, string $column, mixed $value, string $operator): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        match ($operator) {
            'greaterThan' => $query->where($column, '>', $value),
            'lessThan' => $query->where($column, '<', $value),
            'notEqual' => $query->whereDate($column, '!=', $value),
            default => $query->whereDate($column, '=', $value), // 'equals'
        };
    }

    /**
     * number_range / date_range: both bounds → whereBetween; a single bound →
     * the matching gte/lte comparison.
     *
     * @param  callable(mixed): (int|float|string|null)  $normalize
     */
    private function applyRange(Builder $query, string $column, mixed $value, callable $normalize): void
    {
        if (! is_array($value)) {
            return;
        }

        $from = $normalize($value['from'] ?? null);
        $to = $normalize($value['to'] ?? null);

        if ($from !== null && $to !== null) {
            $query->whereBetween($column, [$from, $to]);

            return;
        }

        if ($from !== null) {
            $query->where($column, '>=', $from);
        }

        if ($to !== null) {
            $query->where($column, '<=', $to);
        }
    }

    /**
     * select/enum/radio/autocomplete(single): bound equality.
     */
    private function applyEquals(Builder $query, string $column, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query->where($column, '=', $value);
    }

    /**
     * multiselect/autocomplete_multi: WHERE column IN (?, ?...), cardinality
     * capped regardless of the caller.
     */
    private function applyWhereIn(Builder $query, string $column, mixed $value): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        $clean = array_slice(
            array_values(array_filter($value, static fn (mixed $item): bool => is_scalar($item))),
            0,
            self::MAX_VALUES,
        );

        if ($clean !== []) {
            $query->whereIn($column, $clean);
        }
    }

    /**
     * relation/async_search: `whereHas($target, ...)` matched on the related
     * model's own primary key — generic for any BelongsTo/BelongsToMany/
     * HasMany relation, no domain knowledge required. Single value → `where`;
     * multiple → bound `whereIn`, capped.
     */
    private function applyRelation(Builder $query, string $relation, mixed $value, bool $multiple): void
    {
        if ($multiple) {
            if (! is_array($value) || $value === []) {
                return;
            }

            $ids = array_slice(
                array_values(array_filter($value, static fn (mixed $item): bool => is_scalar($item))),
                0,
                self::MAX_VALUES,
            );

            if ($ids === []) {
                return;
            }

            $query->whereHas($relation, static function (Builder $related) use ($ids): void {
                // Qualified: a BelongsToMany's whereHas joins the pivot table,
                // where a bare `id` would be ambiguous between the two tables.
                $related->whereIn($related->getModel()->getQualifiedKeyName(), $ids);
            });

            return;
        }

        if (! is_scalar($value) || $value === '') {
            return;
        }

        $query->whereHas($relation, static function (Builder $related) use ($value): void {
            $related->where($related->getModel()->getQualifiedKeyName(), '=', $value);
        });
    }
}
