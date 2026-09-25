<?php

declare(strict_types=1);

namespace App\Services\Table;

use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Query application for an already-validated custom filter Rules payload
 * (spec 0158): `(all of `and`) OR (any of `or`)`, each Rule translated into
 * the exact payload a single AG Grid column filter would send and applied
 * through `TableQueryBuilder::applyColumnFilter()` — the SAME path
 * `filterModel` uses (derived columns via `applyDerivedFilter()`, then
 * `FilterApplier`), so a column already wired for the grid needs no extra
 * code to work in a rule. `TableQueryBuilder` is passed as a PARAMETER
 * (never constructor-injected) so it can itself depend on this class without
 * a circular constructor dependency.
 *
 * DATE is the one type this class does NOT delegate to FilterApplier for
 * real columns: FilterApplier's `greaterThan`/`lessThan`/`inRange` compare
 * the raw column value, which is imprecise on a DATETIME column (spec 0158
 * contract: "confronto sul giorno portabile, date e datetime, SQLite e
 * MySQL"). `applyDerivedFilter()` is still tried FIRST (a derived date
 * column owns its own precision); only the real-column fallback uses
 * `whereDate()` directly. `lte`/`gte` are built as `NOT(gt)`/`NOT(lt)` (no
 * portable `lessThanOrEqual`/`greaterThanOrEqual` exists in FilterApplier
 * either), which also naturally excludes NULL rows exactly like every other
 * positive date comparison.
 *
 * Negatives (`not_equals`, `not_in`) are `NOT(positive) OR blank` — spec
 * 0158: "operatori negativi... includono le righe vuote". `blank` itself is
 * always the SAME payload a Set Filter's "(Vuoti)" entry sends —
 * `{filterType: 'set', values: [null]}` — so it reaches the exact blank
 * handling FilterApplier/the relation columns already implement; `not_blank`
 * is `NOT(blank)`.
 *
 * Every rule's contribution is wrapped in its OWN nested `where`/`orWhere`
 * group, so combining it into `and`/`or` never leaks into a sibling rule's
 * own AND/OR conditions, and every value stays a bound parameter (never
 * `whereRaw`).
 */
class CustomFilterRuleApplier
{
    /**
     * @param  array{and?: array<int, mixed>, or?: array<int, mixed>}  $rules
     */
    public function apply(TableQueryBuilder $queryBuilder, TableDefinition $definition, Builder $query, array $rules): void
    {
        $andRules = is_array($rules['and'] ?? null) ? $rules['and'] : [];
        $orRules = is_array($rules['or'] ?? null) ? $rules['or'] : [];

        $query->where(function (Builder $outer) use ($queryBuilder, $definition, $andRules, $orRules): void {
            if ($andRules !== []) {
                $outer->where(function (Builder $andGroup) use ($queryBuilder, $definition, $andRules): void {
                    foreach ($andRules as $rule) {
                        $this->applyRule($queryBuilder, $definition, $andGroup, $rule, 'and');
                    }
                });
            }

            if ($orRules !== []) {
                $outer->orWhere(function (Builder $orGroup) use ($queryBuilder, $definition, $orRules): void {
                    foreach ($orRules as $index => $rule) {
                        $this->applyRule($queryBuilder, $definition, $orGroup, $rule, $index === 0 ? 'and' : 'or');
                    }
                });
            }
        });
    }

    /**
     * @param  Builder<Model>  $group
     */
    private function applyRule(TableQueryBuilder $queryBuilder, TableDefinition $definition, Builder $group, mixed $rule, string $boolean): void
    {
        if (! is_array($rule)) {
            return;
        }

        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? null;

        if (! is_string($field) || ! is_string($operator)) {
            return;
        }

        $columnConfig = $definition->filterableColumnMap()[$field] ?? null;

        if ($columnConfig === null) {
            return; // defensive: FormRequest already 422s an out-of-allow-list field
        }

        $type = CustomFilterRuleValidator::resolveType($columnConfig);

        if ($type === null) {
            return;
        }

        $value = $rule['value'] ?? null;

        $this->addGroup($group, $boolean, function (Builder $inner) use ($queryBuilder, $definition, $field, $columnConfig, $type, $operator, $value): void {
            $this->applyCondition($queryBuilder, $definition, $inner, $field, $columnConfig, $type, $operator, $value);
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     */
    private function applyCondition(TableQueryBuilder $queryBuilder, TableDefinition $definition, Builder $query, string $field, array $columnConfig, string $type, string $operator, mixed $value): void
    {
        if ($operator === 'blank') {
            $queryBuilder->applyColumnFilter($definition, $query, $field, $columnConfig, $this->blankPayload());

            return;
        }

        if ($operator === 'not_blank') {
            $query->whereNot(fn (Builder $inner) => $queryBuilder->applyColumnFilter($definition, $inner, $field, $columnConfig, $this->blankPayload()));

            return;
        }

        match ($type) {
            'text' => $this->applyTextCondition($queryBuilder, $definition, $query, $field, $columnConfig, $operator, $value),
            'number' => $this->applyNumberCondition($queryBuilder, $definition, $query, $field, $columnConfig, $operator, $value),
            'date' => $this->applyDateCondition($queryBuilder, $definition, $query, $field, $columnConfig, $operator, $value),
            'set' => $this->applySetCondition($queryBuilder, $definition, $query, $field, $columnConfig, $operator, $value),
            'boolean' => $queryBuilder->applyColumnFilter($definition, $query, $field, $columnConfig, ['filterType' => 'boolean', 'values' => [(bool) $value]]),
            default => null,
        };
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     */
    private function applyTextCondition(TableQueryBuilder $queryBuilder, TableDefinition $definition, Builder $query, string $field, array $columnConfig, string $operator, mixed $value): void
    {
        $stringValue = is_string($value) ? $value : '';

        if ($operator === 'not_equals') {
            $query->whereNot(fn (Builder $inner) => $queryBuilder->applyColumnFilter($definition, $inner, $field, $columnConfig, ['filterType' => 'text', 'type' => 'equals', 'filter' => $stringValue]))
                ->orWhere(fn (Builder $inner) => $queryBuilder->applyColumnFilter($definition, $inner, $field, $columnConfig, $this->blankPayload()));

            return;
        }

        $agType = $operator === 'contains' ? 'contains' : 'equals';
        $queryBuilder->applyColumnFilter($definition, $query, $field, $columnConfig, ['filterType' => 'text', 'type' => $agType, 'filter' => $stringValue]);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     */
    private function applyNumberCondition(TableQueryBuilder $queryBuilder, TableDefinition $definition, Builder $query, string $field, array $columnConfig, string $operator, mixed $value): void
    {
        if ($operator === 'between' && is_array($value)) {
            $queryBuilder->applyColumnFilter($definition, $query, $field, $columnConfig, [
                'filterType' => 'number', 'type' => 'inRange', 'filter' => $value[0] ?? null, 'filterTo' => $value[1] ?? null,
            ]);

            return;
        }

        $agType = match ($operator) {
            'lt' => 'lessThan',
            'lte' => 'lessThanOrEqual',
            'gt' => 'greaterThan',
            'gte' => 'greaterThanOrEqual',
            default => 'equals',
        };

        $queryBuilder->applyColumnFilter($definition, $query, $field, $columnConfig, ['filterType' => 'number', 'type' => $agType, 'filter' => $value]);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     */
    private function applySetCondition(TableQueryBuilder $queryBuilder, TableDefinition $definition, Builder $query, string $field, array $columnConfig, string $operator, mixed $value): void
    {
        $values = is_array($value)
            ? array_slice(array_values(array_filter($value, static fn ($item): bool => is_string($item) && $item !== '')), 0, CustomFilterRuleValidator::MAX_SET_VALUES)
            : [];

        if ($operator === 'not_in') {
            $query->whereNot(fn (Builder $inner) => $queryBuilder->applyColumnFilter($definition, $inner, $field, $columnConfig, ['filterType' => 'set', 'values' => $values]))
                ->orWhere(fn (Builder $inner) => $queryBuilder->applyColumnFilter($definition, $inner, $field, $columnConfig, $this->blankPayload()));

            return;
        }

        $queryBuilder->applyColumnFilter($definition, $query, $field, $columnConfig, ['filterType' => 'set', 'values' => $values]);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     */
    private function applyDateCondition(TableQueryBuilder $queryBuilder, TableDefinition $definition, Builder $query, string $field, array $columnConfig, string $operator, mixed $value): void
    {
        if ($operator === 'lte') {
            $query->whereNot(fn (Builder $inner) => $this->applyDateCondition($queryBuilder, $definition, $inner, $field, $columnConfig, 'gt', $value));

            return;
        }

        if ($operator === 'gte') {
            $query->whereNot(fn (Builder $inner) => $this->applyDateCondition($queryBuilder, $definition, $inner, $field, $columnConfig, 'lt', $value));

            return;
        }

        [$from, $to] = $this->resolveDateBounds($operator, $value);

        if ($from === null) {
            return;
        }

        $agType = match ($operator) {
            'lt' => 'lessThan',
            'gt' => 'greaterThan',
            'between', 'this_week', 'this_month', 'last_n_days' => 'inRange',
            default => 'equals', // equals, today
        };

        $derivedPayload = ['filterType' => 'date', 'type' => $agType, 'dateFrom' => $from];

        if ($to !== null) {
            $derivedPayload['dateTo'] = $to;
        }

        if ($definition->applyDerivedFilter($query, $field, $columnConfig, $derivedPayload)) {
            return;
        }

        $this->applyRealColumnDate($query, $field, $operator, $from, $to);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyRealColumnDate(Builder $query, string $column, string $operator, string $from, ?string $to): void
    {
        match ($operator) {
            'lt' => $query->whereDate($column, '<', $from),
            'gt' => $query->whereDate($column, '>', $from),
            'between', 'this_week', 'this_month', 'last_n_days' => $query->whereDate($column, '>=', $from)->whereDate($column, '<=', $to),
            default => $query->whereDate($column, '=', $from), // equals, today
        };
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveDateBounds(string $operator, mixed $value): array
    {
        return match ($operator) {
            'equals', 'lt', 'lte', 'gt', 'gte' => [is_string($value) ? $value : null, null],
            'between' => is_array($value) && array_is_list($value)
                ? [is_string($value[0] ?? null) ? $value[0] : null, is_string($value[1] ?? null) ? $value[1] : null]
                : [null, null],
            'today' => [Carbon::today()->toDateString(), null],
            'this_week' => [Carbon::today()->startOfWeek()->toDateString(), Carbon::today()->endOfWeek()->toDateString()],
            'this_month' => [Carbon::today()->startOfMonth()->toDateString(), Carbon::today()->endOfMonth()->toDateString()],
            'last_n_days' => $this->lastNDaysBounds($value),
            default => [null, null],
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function lastNDaysBounds(mixed $value): array
    {
        $n = max(1, (int) $value);
        $today = Carbon::today();

        return [$today->copy()->subDays($n - 1)->toDateString(), $today->toDateString()];
    }

    /**
     * @return array{filterType: string, values: array<int, null>}
     */
    private function blankPayload(): array
    {
        return ['filterType' => 'set', 'values' => [null]];
    }

    /**
     * @param  Builder<Model>  $group
     * @param  callable(Builder<Model>): mixed  $callback
     */
    private function addGroup(Builder $group, string $boolean, callable $callback): void
    {
        $boolean === 'or' ? $group->orWhere($callback) : $group->where($callback);
    }
}
