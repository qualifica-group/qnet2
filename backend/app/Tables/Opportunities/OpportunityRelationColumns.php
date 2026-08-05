<?php

namespace App\Tables\Opportunities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The GENERIC relation-derived column machinery for the `opportunities`
 * domain (spec 0040, amendment rev.3), extracted out of
 * OpportunitiesTableDefinition (file-size split, engineering.md §6):
 * `registry`/`referent`/`commercial`/`supervisor`/`source` (own-FK, simple
 * relation-name columns), `managers`
 * (opportunity_user pivot, to-many) and `product_category`/
 * `business_function` (AGGREGATED to-many via `productLines`) — a
 * `whereHas` set filter (allow-listed columns only, never orderByRaw/
 * whereRaw on raw input — backend.md §8), a correlated subquery sort for the
 * simple relations, and Excel-like distinct values (spec 0004/0005) for all
 * three shapes.
 *
 * `operational_site` (spec 0056) is deliberately NOT handled here: the site
 * has no own name (its identity is its primary address), so it is delegated
 * directly by the table definition to the shared
 * App\Tables\Shared\OperationalSiteColumn instead. `status` (spec 0082) is
 * likewise delegated, to OpportunityStatusColumn: it is COMPUTED from the
 * row's quotes, backed by no FK at all.
 */
final class OpportunityRelationColumns
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Simple (single-hop) relation-name derived columns: relation accessor,
     * related table and owning FK column, keyed by the derived column id.
     * `commercial`/`supervisor` both resolve to their own FK — never the
     * plain `referent_id`/`source_id` used by other columns.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'registry' => ['relation' => 'registry', 'table' => 'registries', 'fk' => 'registry_id'],
        'referent' => ['relation' => 'referent', 'table' => 'referents', 'fk' => 'referent_id'],
        'commercial' => ['relation' => 'commercial', 'table' => 'referents', 'fk' => 'commercial_id'],
        'supervisor' => ['relation' => 'supervisor', 'table' => 'users', 'fk' => 'supervisor_id'],
        'source' => ['relation' => 'source', 'table' => 'sources', 'fk' => 'source_id'],
    ];

    /**
     * Aggregated (to-many, via `productLines`) derived columns: the nested
     * relation path, the related table and the FK column on
     * `opportunity_product_lines` pointing to it (amendment rev.3 — the
     * former single `business_function_id`/`product_category_id` columns on
     * `opportunities` no longer exist).
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array AGGREGATED_RELATIONS = [
        'product_category' => ['relation' => 'productLines.productCategory', 'table' => 'product_categories', 'fk' => 'product_category_id'],
        'business_function' => ['relation' => 'productLines.businessFunction', 'table' => 'business_functions', 'fk' => 'business_function_id'],
    ];

    /**
     * Handle the `registry`/`referent`/`commercial`/`supervisor`/`source`
     * simple-relation set filters, the `managers`
     * (opportunity_user pivot, to-many) set filter, AND the
     * `product_category`/`business_function` AGGREGATED (to-many) set
     * filters, all via `whereHas` on the related row's name (nested dot-path
     * relation for the aggregated ones — Eloquent's own `whereHas` support,
     * no raw SQL). Returns false for any other column id (falls through to
     * the generic engine, or to the caller's own special-cased columns).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        if ($columnId === 'managers') {
            $values = $this->filterValues($filter);

            if ($values !== []) {
                $query->whereHas('managers', static function (Builder $relatedQuery) use ($values): void {
                    $relatedQuery->whereIn('name', $values);
                });
            }

            return true;
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? self::AGGREGATED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $values = $this->filterValues($filter);

        if ($values !== []) {
            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($values): void {
                $relatedQuery->whereIn('name', $values);
            });
        }

        return true;
    }

    /**
     * ORDER BY the related row's name via a correlated subquery for every one
     * of the 5 simple-relation derived columns. `managers` and the 2
     * AGGREGATED (to-many) columns are NOT sortable (returns false — no
     * single related row to order by).
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select('name')
            ->whereColumn("{$config['table']}.id", "opportunities.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the related row's name for
     * each of the 5 simple-relation derived columns, the `managers` pivot's
     * user names, plus the 2 AGGREGATED (to-many) columns via a join through
     * `opportunity_product_lines` — scoped to the rows matching $query.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'managers') {
            return $this->distinctManagerNames($search, $query, $limit);
        }

        if (array_key_exists($columnId, self::AGGREGATED_RELATIONS)) {
            return $this->distinctAggregatedValues(self::AGGREGATED_RELATIONS[$columnId], $search, $query, $limit);
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return null;
        }

        $relatedIds = (clone $query)->whereNotNull($config['fk'])->select($config['fk']);

        return DB::table($config['table'])
            ->whereIn('id', $relatedIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }

    /**
     * Distinct account-manager names among the opportunities matching $query,
     * via a join through the `opportunity_user` pivot — scoped by every OTHER
     * active filter (Excel-like distinct values, spec 0004/0005).
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private function distinctManagerNames(?string $search, Builder $query, int $limit): array
    {
        $opportunityIds = (clone $query)->select('opportunities.id');

        return DB::table('users')
            ->join('opportunity_user', 'opportunity_user.user_id', '=', 'users.id')
            ->whereIn('opportunity_user.opportunity_id', $opportunityIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('users.name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('users.name')
            ->limit($limit)
            ->pluck('users.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  array{relation: string, table: string, fk: string}  $config
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private function distinctAggregatedValues(array $config, ?string $search, Builder $query, int $limit): array
    {
        $opportunityIds = (clone $query)->select('id');

        return DB::table('opportunity_product_lines')
            ->join($config['table'], "{$config['table']}.id", '=', "opportunity_product_lines.{$config['fk']}")
            ->whereIn('opportunity_product_lines.opportunity_id', $opportunityIds)
            ->when($search !== null && $search !== '', function ($builder) use ($config, $search): void {
                $builder->where("{$config['table']}.name", 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy("{$config['table']}.name")
            ->limit($limit)
            ->pluck("{$config['table']}.name")
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
