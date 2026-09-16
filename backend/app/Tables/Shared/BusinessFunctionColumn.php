<?php

namespace App\Tables\Shared;

use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The COMPUTED `business_function` table column (spec 0023), shared by the
 * `product-categories` and `products` domains: both surface a category's
 * EFFECTIVE business function (own or inherited) as a text/set column with
 * no real DB column of its own. Resolution is entirely delegated to
 * CategoryHierarchy (the sole owner of the parent_id walk, spec 0023
 * constraint) and bulk-resolved ONCE per instance (memoized here) into an
 * id → name map, so neither table N+1s while rendering a full page of rows —
 * a fresh instance is built by the container per HTTP request
 * (TableRegistry::resolve()), so the cache never leaks across requests.
 *
 * NOT SORTABLE: the effective value depends on a transitive, unbounded
 * ancestor walk with no portable correlated-subquery expression — ordering
 * it would require either a raw recursive SQL query (forbidden, backend.md
 * §8) or materializing the whole map to sort in PHP (breaks the SSRM
 * contract, which sorts/paginates in SQL). Both domain TableDefinitions
 * declare the column `sortable: false`, which is enough on its own: the
 * generic engine's `sortableColumnIds()` allow-list never reaches a
 * `sortable: false` column, so no `applyDerivedSort` override is needed here.
 */
final class BusinessFunctionColumn
{
    /**
     * Maximum number of names honoured in the set filter. Caps the
     * `categoryIdsForNames()` → WHERE IN cardinality (defence in depth);
     * excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * @var array<int, string|null>|null
     */
    private ?array $namesByCategory = null;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The effective business function name for a single category id, or null.
     */
    public function nameFor(?int $categoryId): ?string
    {
        if ($categoryId === null) {
            return null;
        }

        return $this->namesByCategory()[$categoryId] ?? null;
    }

    /**
     * Derived SET filter on a query whose own `id` IS the category id
     * (product-categories): matches categories whose effective function
     * name is one of $filter's values.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyCategoryIdFilter(Builder $query, array $filter): void
    {
        $names = $this->filterNames($filter);
        $matchesBlank = $this->matchesBlank($filter);

        if ($names === [] && ! $matchesBlank) {
            return;
        }

        $ids = $names === [] ? [] : $this->categoryIdsForNames($names);

        // The blank entry ("(Vuoti)") selects the categories with no effective
        // function at all: they resolve to ids like any named function does, so
        // both sides of the selection stay one bound WHERE IN.
        if ($matchesBlank) {
            $ids = array_values(array_unique(array_merge($ids, $this->categoryIdsWithoutFunction())));
        }

        $query->whereIn('id', $ids);
    }

    /**
     * Derived SET filter on a query whose `category_id` column references
     * the category (products): matches products whose category's effective
     * function name is one of $filter's values.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyCategoryReferenceFilter(Builder $query, array $filter): void
    {
        $names = $this->filterNames($filter);

        if ($names !== []) {
            $query->whereIn('category_id', $this->categoryIdsForNames($names));
        }
    }

    /**
     * Excel-like distinct values (spec 0004/0005): distinct EFFECTIVE
     * function names among $categoryIds (already scoped by the caller's
     * query — every OTHER active filter), search-narrowed, sorted, capped,
     * plus the blank entry when some of them resolve to no function.
     *
     * @param  Collection<int, int>  $categoryIds
     * @return array<int, string|null>
     */
    public function distinctValues(Collection $categoryIds, ?string $search, int $limit): array
    {
        $resolved = $categoryIds->map(fn (int $id): ?string => $this->nameFor($id));

        $names = $resolved->filter(static fn (?string $name): bool => $name !== null)->unique();

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $names = $names->filter(static fn (string $name): bool => str_contains(mb_strtolower($name), $needle));
        }

        $values = $names->sort()->values()->take($limit)->all();

        // `null` is AG Grid's blank entry ("(Vuoti)"): offered whenever a scoped
        // category inherits no function at all, so those rows stay reachable.
        // Skipped while searching — the blank entry matches no search term.
        if (($search === null || $search === '') && $resolved->contains(null)) {
            array_unshift($values, null);
        }

        return $values;
    }

    /**
     * Category ids whose EFFECTIVE business function name is one of $names.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    private function categoryIdsForNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return collect($this->namesByCategory())
            ->filter(static fn (?string $name): bool => $name !== null && in_array($name, $names, true))
            ->keys()
            ->all();
    }

    /**
     * Category ids with no EFFECTIVE business function (own or inherited).
     *
     * @return array<int, int>
     */
    private function categoryIdsWithoutFunction(): array
    {
        return collect($this->namesByCategory())
            ->filter(static fn (?string $name): bool => $name === null)
            ->keys()
            ->all();
    }

    /**
     * @return array<int, string|null>
     */
    private function namesByCategory(): array
    {
        return $this->namesByCategory ??= $this->hierarchy->effectiveBusinessFunctionNames();
    }

    /**
     * Extract, sanitize and cap the string values of a set filter payload.
     *
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterNames(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        $clean = array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        ));

        return array_slice($clean, 0, self::MAX_FILTER_VALUES);
    }

    /**
     * Whether the set filter payload carries AG Grid's blank entry.
     *
     * @param  array<string, mixed>  $filter
     */
    private function matchesBlank(array $filter): bool
    {
        $values = $filter['values'] ?? null;

        return is_array($values) && in_array(null, $values, true);
    }
}
