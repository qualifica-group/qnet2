<?php

namespace App\Services;

use App\DataObjects\Table\DistinctValuesResult;
use App\DataObjects\Table\RowsResult;
use App\Models\User;
use App\Services\Table\CustomFilterRuleApplier;
use App\Services\Table\FilterApplier;
use App\Services\Table\TableQueryBuilder;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic SSRM engine for the domain-driven Table framework.
 *
 * Holds the SINGLE copy of the security-critical AG Grid SSRM logic (lifted
 * verbatim from the old UserTableService::rows() and its helpers): translate
 * startRow/endRow → offset/limit with a MAX_LIMIT cap, apply sortModel/
 * filterModel against the definition's strict sortable/filterable whitelist
 * using bound query-builder calls only (never raw user input in SQL), cap
 * set-filter cardinality, paginate, then map each row + attach the per-row
 * actions[] via the definition's Policy hooks. The filter/search/sort query
 * construction itself is delegated to the injected TableQueryBuilder (spec
 * 0014 query-refactor), shared verbatim with the generic export engine;
 * per-type filter branches (text/number/boolean/date/set/multi/combined) live
 * one level deeper in FilterApplier (spec 0004).
 *
 * It operates ONLY through the resolved TableDefinition (baseQuery, columns,
 * mapRow, actionsFor, applyDerivedFilter), so every domain inherits the exact
 * same anti-injection + per-row-authorization behavior. No behavioral change
 * vs ADR-0001's users-specific implementation.
 */
class TableService
{
    /**
     * Fixed value list offered for a boolean column's Set Filter. Both options
     * are ALWAYS shown (as raw 1/0, localized to Sì/No on the frontend) rather
     * than only the values currently present in the data, so the user can always
     * filter by either state. Emitted as strings to match the generic column
     * fallback and round-trip cleanly through the set/boolean filter.
     *
     * @var array<int, string>
     */
    private const array BOOLEAN_FILTER_VALUES = ['1', '0'];

    public function __construct(
        private readonly TableQueryBuilder $queryBuilder,
        private readonly FilterApplier $filterApplier,
        private readonly CustomFilterRuleApplier $customFilterRuleApplier,
    ) {}

    /**
     * Execute the SSRM query and return the rows + total for the envelope.
     *
     * @param  array{startRow: int, endRow: int, sortModel?: array<int, array<string, mixed>>, filterModel?: array<string, array<string, mixed>>, search?: string|null, advancedFilters?: array<string, mixed>, customFilterRules?: array<string, mixed>|null, tree?: bool, treeParentId?: int|null, kanbanGroup?: array{by: string, key: int|string}|null}  $payload
     */
    public function rows(TableDefinition $definition, User $actor, array $payload): RowsResult
    {
        $offset = max(0, (int) $payload['startRow']);
        $limit = min($definition->maxRowsLimit(), max(1, (int) $payload['endRow'] - $offset));

        $query = $definition->baseQuery();

        // Spec 0158: a custom filter rule set REPLACES filterModel/
        // advancedFilters entirely (even required-default advanced filters)
        // — search, scope and sort below still apply on top.
        $customFilterRules = $payload['customFilterRules'] ?? null;

        if ($customFilterRules !== null) {
            $this->customFilterRuleApplier->apply($this->queryBuilder, $definition, $query, $customFilterRules);
        } else {
            $this->queryBuilder->applyFilters($definition, $query, $payload['filterModel'] ?? []);
            // Second-level, backend-driven advanced filters (spec 0032) — AND-combined
            // with the column filters above and the quick-search below.
            $this->queryBuilder->applyAdvancedFilters($definition, $query, $payload['advancedFilters'] ?? []);
        }

        $this->queryBuilder->applySearch($definition, $query, $payload['search'] ?? null);

        // Spec 0157, D-1: tree/hierarchical row scoping (Sintetica), AFTER
        // every filter/search above so it combines with the rest of the
        // active query — `treeParentId` alone (no `tree` flag) is also tree
        // mode (root rows when absent). TableController has ALREADY
        // authorized a non-null `treeParentId` (403 boundary) and
        // TableRowsRequest has already 422'd a domain that does not
        // supportsTree(), so this is safe to apply unconditionally here.
        if (($payload['tree'] ?? false) === true || array_key_exists('treeParentId', $payload)) {
            $definition->applyTreeScope($query, $payload['treeParentId'] ?? null);
        }

        // Spec 0164, D-2: server-side Kanban column grouping, AFTER every
        // filter/search/tree above so a column combines with the rest of
        // the active query. Mutually exclusive with tree by construction
        // (TableRowsRequest 422s the combination) and only ever reached for
        // a domain that supportsKanbanGroups() (422 otherwise, before this
        // runs).
        $kanbanGroup = $payload['kanbanGroup'] ?? null;

        if (is_array($kanbanGroup)) {
            $definition->applyKanbanGroupScope($query, $kanbanGroup);
        }

        $total = (clone $query)->count();
        // Spec 0156, D-3: computed over the SAME filtered-but-unsorted query
        // as $total, never the paginated page — a footer total that agreed
        // with the page instead of the filtered set would be the wrong
        // number the moment a second page exists.
        $aggregates = $definition->aggregates(clone $query);

        $this->queryBuilder->applySorting($definition, $query, $payload['sortModel'] ?? []);

        /** @var array<int, Model> $models */
        $models = $query->offset($offset)->limit($limit)->get()->all();

        $items = array_map(
            fn (Model $row): array => $this->mapRowWithMeta($definition, $actor, $row),
            $models,
        );

        return new RowsResult(
            items: $items,
            total: $total,
            offset: $offset,
            limit: $limit,
            aggregates: $aggregates,
        );
    }

    /**
     * Distinct values for a single filterable column (Excel-like set filter),
     * scoped by the filters active on every OTHER column — the target column
     * never auto-restricts its own list. Delegates to the definition's
     * distinctValues() hook for DERIVED columns (no real DB column, e.g.
     * `roles`); falls back to a plain `SELECT DISTINCT` on the real column.
     *
     * Fetches one value beyond `$limit` so `hasMore` reflects real truncation
     * without a second COUNT query. A `null` in the list is the blank entry
     * ("(Vuoti)"), never a real value: see capValues().
     *
     * @param  array<string, array<string, mixed>>  $filterModel
     */
    public function distinctValues(
        TableDefinition $definition,
        User $actor,
        string $columnId,
        ?string $search,
        array $filterModel,
        int $limit,
    ): DistinctValuesResult {
        $filterable = $definition->filterableColumnMap();

        if (! array_key_exists($columnId, $filterable)) {
            return new DistinctValuesResult(values: [], hasMore: false);
        }

        $columnConfig = $filterable[$columnId];

        // COMPUTED columns with no discrete value list (e.g. a formatted
        // address string, an aggregate count) declare hasFilterValues=false:
        // there is no real DB column to SELECT DISTINCT on, so the generic
        // fallback must never be reached for them (defence in depth against
        // a 500 — see spec 0004/0005 AC-016..018). Resolver-backed derived
        // columns (roles/geo/user_type/permissions) keep hasFilterValues=true
        // and are unaffected.
        if (($columnConfig['hasFilterValues'] ?? true) === false) {
            return new DistinctValuesResult(values: [], hasMore: false);
        }

        $query = $definition->baseQuery();

        unset($filterModel[$columnId]); // Excel behaviour: never self-restrict.
        $this->queryBuilder->applyFilters($definition, $query, $filterModel);

        $fetchLimit = $limit + 1;
        $resolved = $definition->distinctValues($actor, $columnId, $columnConfig, $search, $query, $fetchLimit);
        $values = $resolved ?? $this->distinctForColumn($columnConfig, $query, $columnId, $search, $fetchLimit);

        return $this->capValues($values, $limit);
    }

    /**
     * Cap the resolved value list without letting the blank entry consume the
     * budget: `null` (AG Grid's "(Vuoti)") is lifted out before the cap and put
     * back in front, so neither the cap nor `hasMore` depends on whether the
     * column happens to hold empty cells — same rule for the generic fallback
     * and for a definition's own distinctValues() hook.
     *
     * @param  array<int, string|null>  $values
     */
    private function capValues(array $values, int $limit): DistinctValuesResult
    {
        $hasBlank = in_array(null, $values, true);
        $present = array_values(array_filter($values, static fn (?string $value): bool => $value !== null));

        $capped = array_slice($present, 0, $limit);

        return new DistinctValuesResult(
            values: $hasBlank ? array_merge([null], $capped) : $capped,
            hasMore: count($present) > $limit,
        );
    }

    /**
     * Map a single already-resolved row into its full grid shape (mapRow +
     * actions + editable) — reused by the PATCH endpoint's response, whose
     * contract requires "the same shape as POST /rows" (spec 0053, D-9), so
     * the row-shape assembly (D-4: `editable` attached exactly where
     * `actions` is) lives in this ONE place regardless of caller.
     *
     * @return array<string, mixed>
     */
    public function mapSingleRow(TableDefinition $definition, User $actor, Model $row): array
    {
        return $this->mapRowWithMeta($definition, $actor, $row);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRowWithMeta(TableDefinition $definition, User $actor, Model $row): array
    {
        $mapped = $definition->mapRow($actor, $row);
        $mapped['actions'] = $definition->actionsFor($actor, $row);
        // Per-row inline-edit authorization (spec 0053, D-4), orthogonal to
        // the per-column `editable` allow-list emitted by GET /columns.
        $mapped['editable'] = $definition->authorizeUpdate($actor, $row);

        return $mapped;
    }

    /**
     * Fallback value list for a real DB column with no definition override.
     *
     * A boolean column always offers BOTH states (not merely the ones present
     * in the data) so the user can filter by either; every other type falls
     * back to a plain `SELECT DISTINCT` over the actual values.
     *
     * @param  array<string, mixed>  $columnConfig
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private function distinctForColumn(array $columnConfig, Builder $query, string $column, ?string $search, int $limit): array
    {
        if (($columnConfig['type'] ?? null) === 'boolean') {
            return self::BOOLEAN_FILTER_VALUES;
        }

        return $this->distinctFromColumn($columnConfig, $query, $column, $search, $limit);
    }

    /**
     * Plain `SELECT DISTINCT` fallback for a real DB column: optional
     * case-insensitive substring search (bound, LIKE-escaped), sorted, capped,
     * plus the blank entry when the scoped rows hold empty cells.
     *
     * @param  array<string, mixed>  $columnConfig
     * @param  Builder<Model>  $query
     * @return array<int, string|null>
     */
    private function distinctFromColumn(array $columnConfig, Builder $query, string $column, ?string $search, int $limit): array
    {
        $isTextual = $this->filterApplier->treatsEmptyStringAsBlank($columnConfig);

        $clone = clone $query;

        if ($search !== null && $search !== '') {
            $clone->where($column, 'like', '%'.$this->filterApplier->escapeLike($search).'%');
        }

        $values = $clone->whereNotNull($column)
            ->when($isTextual, static fn (Builder $builder): Builder => $builder->where($column, '<>', ''))
            ->distinct()
            ->orderBy($column)
            ->limit($limit)
            ->pluck($column)
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        // `null` is AG Grid's own blank entry: the Set Filter renders it as
        // "(Vuoti)" and sends it straight back inside the filter model, so an
        // empty cell becomes selectable like any other value.
        if ($this->hasBlankValues($query, $column, $isTextual, $search)) {
            array_unshift($values, null);
        }

        return $values;
    }

    /**
     * Whether the scoped rows hold at least one empty cell on this column —
     * NULL always, plus the empty string on a textual column, where the two are
     * indistinguishable to the user. Skipped while a substring search is
     * active: the blank entry matches no search term.
     *
     * @param  Builder<Model>  $query
     */
    private function hasBlankValues(Builder $query, string $column, bool $isTextual, ?string $search): bool
    {
        if ($search !== null && $search !== '') {
            return false;
        }

        return (clone $query)
            ->where(static function (Builder $group) use ($column, $isTextual): void {
                $group->whereNull($column);

                if ($isTextual) {
                    $group->orWhere($column, '=', '');
                }
            })
            ->exists();
    }
}
