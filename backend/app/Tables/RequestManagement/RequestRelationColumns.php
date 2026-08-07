<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The GENERIC relation-derived column machinery for the `request-management`
 * domain (spec 0086: the row is now a `quotes` record, D-1), extracted out of
 * RequestManagementTableDefinition (file-size split, engineering.md §6).
 * Mirrors `App\Tables\Contracts\ContractRelationColumns`' shape split for a
 * domain whose display data spans a relation chain:
 *
 *  - OPPORTUNITY_RELATIONS: `source` — the field lives on the row's
 *    OPPORTUNITY (`opportunities.source_id`); the relation path carries the
 *    `opportunity.` prefix and every subquery joins through `opportunities`,
 *    correlated via `opportunities.id = quotes.opportunity_id`.
 *  - AGGREGATED_RELATIONS: `product_categories` — to-many via
 *    `opportunity.productLines.productCategory`; filterable (`whereHas`
 *    dot-path, no SQL change needed) but never sortable.
 *  - OPPORTUNITY_SCALAR_COLUMNS: `general_notes`/`next_callback_at` — real
 *    `opportunities` columns with no relation label, reached via the SAME
 *    `opportunity` relation: filtering scopes the generic FilterApplier
 *    inside a `whereHas('opportunity', ...)` closure, sorting uses a
 *    correlated subquery on the real `opportunities` column. Both declare
 *    `hasFilterValues: false` in RequestColumnCatalog (mirrors
 *    ContractColumnCatalog's QUOTE_SCALAR_COLUMNS): TableService never calls
 *    distinctValues() for them.
 *
 * `operator_ga2` (spec 0086, D-3: now `quote.supervisor`, a real FK on
 * `quotes` itself) is deliberately NOT handled here — corrected spec 0086
 * AC-011: the user directive behind this migration keeps filters/sort/
 * behaviour unchanged, only the underlying model moves; the column was never
 * sortable/filterable before and stays that way, only its source changed
 * (RequestRowMapper's `userSummary($row->supervisor)`). `operational_site`
 * (spec 0056) stays delegated to the shared App\Tables\Shared\OperationalSiteColumn:
 * a real FK on `quotes` itself (D-6), unchanged by this migration, with no
 * own `name` column.
 *
 * Every column id reaching this class comes from the definition's own static
 * catalogue (RequestColumnCatalog) — never client input — so every relation
 * path, table and column name here is an allow-list value, never
 * interpolated from a request (backend.md §8).
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
    private const array OPPORTUNITY_RELATIONS = [
        'source' => ['relation' => 'opportunity.source', 'table' => 'sources', 'fk' => 'source_id'],
    ];

    /**
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array AGGREGATED_RELATIONS = [
        'product_categories' => ['relation' => 'opportunity.productLines.productCategory', 'table' => 'product_categories', 'fk' => 'product_category_id'],
    ];

    /**
     * @var array<int, string>
     */
    private const array OPPORTUNITY_SCALAR_COLUMNS = ['general_notes', 'next_callback_at'];

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (in_array($columnId, self::OPPORTUNITY_SCALAR_COLUMNS, true)) {
            $query->whereHas('opportunity', function (Builder $opportunityQuery) use ($columnId, $columnConfig, $filter): void {
                $this->filterApplier->apply($opportunityQuery, $columnId, $columnConfig, $filter);
            });

            return true;
        }

        $config = self::OPPORTUNITY_RELATIONS[$columnId]
            ?? self::AGGREGATED_RELATIONS[$columnId]
            ?? null;

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
     * ORDER BY the derived value via a correlated subquery — never a
     * row-multiplying JOIN on the main query. `product_categories` (the
     * AGGREGATED to-many column) is NOT sortable (returns false — no single
     * related row to order by).
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $subquery = $this->subqueryFor($columnId);

        if ($subquery === null) {
            return false;
        }

        $query->orderBy($subquery, $direction);

        return true;
    }

    private function subqueryFor(string $columnId): ?QueryBuilder
    {
        if (in_array($columnId, self::OPPORTUNITY_SCALAR_COLUMNS, true)) {
            return $this->opportunityScalarSubquery($columnId);
        }

        $throughConfig = self::OPPORTUNITY_RELATIONS[$columnId] ?? null;

        return $throughConfig === null ? null : $this->opportunityRelationSubquery($throughConfig['table'], $throughConfig['fk']);
    }

    private function opportunityRelationSubquery(string $table, string $fk): QueryBuilder
    {
        return DB::table($table)
            ->select("{$table}.name")
            ->join('opportunities', "opportunities.{$fk}", '=', "{$table}.id")
            ->whereColumn('opportunities.id', 'quotes.opportunity_id')
            ->limit(1);
    }

    private function opportunityScalarSubquery(string $column): QueryBuilder
    {
        return DB::table('opportunities')
            ->select($column)
            ->whereColumn('opportunities.id', 'quotes.opportunity_id')
            ->limit(1);
    }

    /**
     * Excel-like distinct values (spec 0004/0005): `source`'s related row
     * name, plus `product_categories` via a join through
     * `opportunity_product_lines` — scoped to the rows matching $query.
     * OPPORTUNITY_SCALAR_COLUMNS are never reached here (they declare
     * `hasFilterValues: false`, so TableService never calls this for them).
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if (array_key_exists($columnId, self::AGGREGATED_RELATIONS)) {
            return $this->distinctAggregatedValues(self::AGGREGATED_RELATIONS[$columnId], $search, $query, $limit);
        }

        $throughConfig = self::OPPORTUNITY_RELATIONS[$columnId] ?? null;

        if ($throughConfig !== null) {
            $opportunityIds = (clone $query)->select('quotes.opportunity_id');
            $relatedIds = DB::table('opportunities')->whereIn('id', $opportunityIds)->whereNotNull($throughConfig['fk'])->select($throughConfig['fk']);

            return $this->distinctNames($throughConfig['table'], $relatedIds, $search, $limit);
        }

        return null;
    }

    /**
     * `whereHas` on a relation's own `name`, bound and never raw — shared by
     * every DERIVED-column set filter (applyFilter) and its advanced-filter
     * twin (RequestManagementTableDefinition::applyAdvancedFilter). Dot-path
     * relations (e.g. `opportunity.source`) are supported natively by
     * Eloquent's own `whereHas()`.
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
     * @param  QueryBuilder|Builder<Model>  $ids
     * @return array<int, string>
     */
    private function distinctNames(string $table, QueryBuilder|Builder $ids, ?string $search, int $limit): array
    {
        return DB::table($table)
            ->whereIn('id', $ids)
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
     * @param  array{relation: string, table: string, fk: string}  $config
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private function distinctAggregatedValues(array $config, ?string $search, Builder $query, int $limit): array
    {
        $opportunityIds = (clone $query)->select('quotes.opportunity_id');

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
