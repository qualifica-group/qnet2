<?php

namespace App\Services\Table;

use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Single, reusable place for the security-critical SSRM query-construction
 * logic (spec 0014 query-refactor): filterModel + global search + sortModel
 * applied against a TableDefinition's allow-lists, injection-safe (bound
 * parameters only, LIKE escaped, no whereRaw/orderByRaw on input).
 *
 * Lifted VERBATIM out of the former TableService private methods (same
 * behavior, same tests): `TableService::rows()` now delegates here, and
 * `ExportService` (spec 0014) reuses it too, so both the interactive grid and
 * the generic export engine share exactly one implementation of "what rows
 * does the current grid state resolve to" — DRY, and a single security
 * review surface.
 */
class TableQueryBuilder
{
    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * Apply filterModel, global search, then sortModel (in that order) to the
     * definition's baseQuery(). This is the one-shot entry point ExportService
     * uses to stream the exact rows the grid would show.
     *
     * @param  array{sortModel?: array<int, array<string, mixed>>, filterModel?: array<string, array<string, mixed>>, search?: string|null, advancedFilters?: array<string, mixed>}  $state
     * @return Builder<Model>
     */
    public function build(TableDefinition $definition, array $state): Builder
    {
        $query = $definition->baseQuery();

        $this->applyFilters($definition, $query, $state['filterModel'] ?? []);
        $this->applyAdvancedFilters($definition, $query, $state['advancedFilters'] ?? []);
        $this->applySearch($definition, $query, $state['search'] ?? null);
        $this->applySorting($definition, $query, $state['sortModel'] ?? []);

        return $query;
    }

    /**
     * Apply whitelisted filters to the query.
     *
     * SECURITY: only keys present in the definition's filterable whitelist are
     * honoured; every column is resolved to a real DB column name from the
     * definition and every value is passed as a bound parameter via the query
     * builder — never interpolated into SQL. Derived columns (e.g. `roles`) are
     * delegated to the definition's applyDerivedFilter hook.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, array<string, mixed>>  $filterModel
     */
    public function applyFilters(TableDefinition $definition, Builder $query, array $filterModel): void
    {
        $filterable = $definition->filterableColumnMap();

        foreach ($filterModel as $columnId => $filter) {
            if (! array_key_exists($columnId, $filterable)) {
                continue; // not whitelisted — ignore defensively (FormRequest already 422s)
            }

            if (! is_array($filter)) {
                continue;
            }

            // Derived columns (no real DB column) are handled by the definition.
            if ($definition->applyDerivedFilter($query, $columnId, $filterable[$columnId], $filter)) {
                continue;
            }

            $this->filterApplier->apply($query, $columnId, $filterable[$columnId], $filter);
        }
    }

    /**
     * Apply the global quick-search (spec 0009): a single grouped OR-LIKE over
     * the definition's `searchableColumnIds()` allow-list.
     *
     * SECURITY: the columns come exclusively from the definition's server-side
     * allow-list (never from the request), and the term is a LIKE-escaped bound
     * parameter — never interpolated into SQL. The OR group is wrapped in its
     * own closure so it AND-combines with any active column filters instead of
     * widening them.
     *
     * A DERIVED searchable column (no real DB column, e.g. `city`/`street` on
     * `operational-sites` — spec 0011) is delegated FIRST to the definition's
     * `applyDerivedSearch()` hook; only when it returns false does the generic
     * plain `orWhere($column, 'like', $pattern)` run against the real column.
     *
     * @param  Builder<Model>  $query
     */
    public function applySearch(TableDefinition $definition, Builder $query, ?string $search): void
    {
        $term = $search === null ? '' : trim($search);

        if ($term === '') {
            return;
        }

        $columns = $definition->searchableColumnIds();

        if ($columns === []) {
            return;
        }

        $pattern = '%'.$this->filterApplier->escapeLike($term).'%';

        $query->where(function (Builder $group) use ($definition, $columns, $pattern): void {
            foreach ($columns as $column) {
                if ($definition->applyDerivedSearch($group, $column, $pattern)) {
                    continue;
                }

                $group->orWhere($column, 'like', $pattern);
            }
        });
    }

    /**
     * Apply the `advancedFilters` payload (spec 0032, second-level backend-
     * driven panel) to the query, combined in AND with every other active
     * filter. A `required` descriptor whose value is OMITTED from the request
     * falls back to its `defaultValue` — defence in depth so a required
     * advanced filter can never leave the table effectively unfiltered
     * (AC-006). Each entry is delegated to `$definition->applyAdvancedFilter()`
     * against its own catalog descriptor.
     *
     * SECURITY: only names present in the definition's advanced-filter
     * catalogue are honoured (allow-list; the FormRequest already 422s
     * anything else — this is defence in depth), and `target` is resolved
     * server-side from that same catalogue, never from the request.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $advancedFilters
     */
    public function applyAdvancedFilters(TableDefinition $definition, Builder $query, array $advancedFilters): void
    {
        $catalog = array_column($definition->advancedFilters(), null, 'name');

        if ($catalog === []) {
            return;
        }

        $resolved = $this->withRequiredDefaults($catalog, $advancedFilters);

        foreach ($resolved as $name => $value) {
            if (! is_string($name) || ! array_key_exists($name, $catalog)) {
                continue; // not whitelisted — ignore defensively (FormRequest already 422s)
            }

            $definition->applyAdvancedFilter($query, $name, $catalog[$name], $value);
        }
    }

    /**
     * A `required` descriptor whose value is absent from the request falls
     * back to its declared `defaultValue` (AC-006).
     *
     * @param  array<string, array<string, mixed>>  $catalog  keyed by descriptor `name`
     * @param  array<string, mixed>  $advancedFilters
     * @return array<string, mixed>
     */
    private function withRequiredDefaults(array $catalog, array $advancedFilters): array
    {
        foreach ($catalog as $name => $descriptor) {
            if (
                ($descriptor['required'] ?? false) === true
                && ! array_key_exists($name, $advancedFilters)
                && array_key_exists('defaultValue', $descriptor)
                && $descriptor['defaultValue'] !== null
            ) {
                $advancedFilters[$name] = $descriptor['defaultValue'];
            }
        }

        return $advancedFilters;
    }

    /**
     * Apply whitelisted sorting. Only columns flagged `sortable` in the
     * definition are accepted; direction is constrained to asc/desc. Both are
     * validated upstream by the FormRequest — this is defence in depth.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, array<string, mixed>>  $sortModel
     */
    public function applySorting(TableDefinition $definition, Builder $query, array $sortModel): void
    {
        $sortable = $definition->sortableColumnIds();
        $applied = false;

        foreach ($sortModel as $sort) {
            $colId = $sort['colId'] ?? null;

            if (! is_string($colId) || ! in_array($colId, $sortable, true)) {
                continue;
            }

            $direction = ($sort['sort'] ?? null) === 'desc' ? 'desc' : 'asc';

            // Derived columns (no real DB column, e.g. user_type/geo) are sorted
            // by the definition; everything else is a plain ORDER BY.
            if (! $definition->applyDerivedSort($query, $colId, $direction)) {
                $query->orderBy($colId, $direction);
            }

            $applied = true;
        }

        if (! $applied) {
            $this->applyDefaultSort($definition, $query, $sortable);
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $sortable
     */
    private function applyDefaultSort(TableDefinition $definition, Builder $query, array $sortable): void
    {
        foreach ($definition->defaultSort() as $sort) {
            $colId = $sort['columnId'] ?? null;

            if (is_string($colId) && in_array($colId, $sortable, true)) {
                $direction = ($sort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

                if (! $definition->applyDerivedSort($query, $colId, $direction)) {
                    $query->orderBy($colId, $direction);
                }
            }
        }
    }
}
