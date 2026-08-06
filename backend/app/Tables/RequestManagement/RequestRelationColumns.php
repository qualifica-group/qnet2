<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The GENERIC relation-derived column machinery for the `request-management`
 * domain (spec 0049), extracted out of RequestManagementTableDefinition
 * (file-size split, engineering.md §6): `source` (own-FK simple relation)
 * and `product_categories` (AGGREGATED to-many via `productLines`)
 * — a `whereHas` set filter on the related row's name (allow-listed columns
 * only, never orderByRaw/whereRaw on raw input — backend.md §8), a
 * correlated subquery sort for the simple relation, and Excel-like distinct
 * values (spec 0004/0005) for both shapes. Mirrors
 * App\Tables\Opportunities\OpportunityRelationColumns.
 *
 * `operational_site` (spec 0056) is deliberately NOT handled here: the site
 * has no own name, so it is delegated directly by the table definition to
 * the shared App\Tables\Shared\OperationalSiteColumn instead. Spec 0083,
 * D-2: `workflow_status` is REMOVED — the Opportunity resolves no working
 * state of its own any more.
 */
final class RequestRelationColumns
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'source' => ['relation' => 'source', 'table' => 'sources', 'fk' => 'source_id'],
    ];

    /**
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array AGGREGATED_RELATIONS = [
        'product_categories' => ['relation' => 'productLines.productCategory', 'table' => 'product_categories', 'fk' => 'product_category_id'],
    ];

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? self::AGGREGATED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $values = $this->filterValues($filter);

        if ($values !== []) {
            $this->applyNameWhereHas($query, $config['relation'], $values);
        }

        return true;
    }

    /**
     * ORDER BY the related row's name via a correlated subquery for
     * `workflow_status`/`source`. `product_categories` (the AGGREGATED to-many column)
     * is NOT sortable (returns false — no single related row to order by).
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
     * Excel-like distinct values (spec 0004/0005): `workflow_status`'s and
     * `source`'s related row name, plus `product_categories` via a join through
     * `opportunity_product_lines` — scoped to the rows matching $query.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
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
     * `whereHas` on a relation's own `name`, bound and never raw — shared by
     * the `workflow_status` derived-column set filter (applyFilter) and its
     * advanced-filter twin (RequestManagementTableDefinition::
     * applyAdvancedFilter).
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyNameWhereHas(Builder $query, string $relation, array $values): void
    {
        $query->whereHas($relation, static function (Builder $relatedQuery) use ($values): void {
            $relatedQuery->whereIn('name', $values);
        });
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
