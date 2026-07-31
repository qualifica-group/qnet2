<?php

declare(strict_types=1);

namespace App\Tables\Contracts;

use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The relation-derived column machinery for the `contracts` domain (spec
 * 0072, MT-04), extracted out of ContractsTableDefinition (file-size split,
 * engineering.md §6).
 *
 * D-1: `contracts` owns almost none of its own display data. The chain runs
 * `contracts.quote_id` -> `quotes` -> `quotes.opportunity_id` ->
 * `opportunities` -> `opportunities.registry_id` -> `registries`, plus
 * `quotes.commercial_id`/`reporter_id` -> `referents` and
 * `quotes.supervisor_id` -> `users`. `contract_status` is the one single-hop
 * relation — a real FK on `contracts` itself, mirroring QuoteRelationColumns'
 * own-FK case.
 *
 * Two column shapes live here:
 *  - RELATION_PATHS: the 6 name-labelled `set` columns (`registry`/
 *    `opportunity`/`commercial`/`reporter`/`supervisor`/`contract_status`) —
 *    a `whereHas` dot-path set filter, a correlated-subquery sort and
 *    Excel-like distinct values (spec 0004/0005), mirroring
 *    QuoteRelationColumns.
 *  - QUOTE_SCALAR_COLUMNS: `code`/`title`/`quote_date`(`quotes.created_at`)/
 *    `revenue_net`/`revenue_vat` — plain `quotes` columns with no relation
 *    label at all, reached via the SAME `quote` relation. Filtering reuses
 *    the generic FilterApplier scoped inside a `whereHas('quote', ...)`
 *    closure (DRY: the exact text/number/date operator set as any real
 *    column, without reimplementing it); sorting uses a correlated subquery
 *    selecting the real `quotes` column. `code`/`title` are also the two
 *    quick-search columns (spec 0009).
 *
 * Every column id reaching this class comes from the definition's own static
 * catalogue (ContractColumnCatalog) — never client input — so every relation
 * path, table and column name here is an allow-list value, never
 * interpolated from a request (backend.md §8).
 */
final class ContractRelationColumns
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /** Every related table projected here calls its display name `name`. */
    private const string LABEL_COLUMN = 'name';

    /**
     * `set`-filterable relation columns -> their `whereHas` dot-path.
     *
     * @var array<string, string>
     */
    private const array RELATION_PATHS = [
        'registry' => 'quote.opportunity.registry',
        'opportunity' => 'quote.opportunity',
        'commercial' => 'quote.commercial',
        'reporter' => 'quote.reporter',
        'supervisor' => 'quote.supervisor',
        'contract_status' => 'contractStatus',
    ];

    /**
     * Own-FK relation columns' [target table, FK column]. `contract_status`
     * is the only one whose FK lives directly on `contracts`; every other
     * RELATION_PATHS entry is reached through `quotes` instead
     * (QUOTE_RELATION_FKS below).
     *
     * @var array<string, array{table: string, fk: string}>
     */
    private const array OWN_FK_RELATIONS = [
        'contract_status' => ['table' => 'contract_statuses', 'fk' => 'contract_status_id'],
    ];

    /**
     * The other 5 RELATION_PATHS entries: [target table, FK column ON
     * `quotes`], reached via `quotes.id = contracts.quote_id`. `registry` is
     * NOT here — it needs one extra hop through `opportunities`, handled
     * separately (registrySortSubquery/registryIds).
     *
     * @var array<string, array{table: string, fk: string}>
     */
    private const array QUOTE_RELATION_FKS = [
        'opportunity' => ['table' => 'opportunities', 'fk' => 'opportunity_id'],
        'commercial' => ['table' => 'referents', 'fk' => 'commercial_id'],
        'reporter' => ['table' => 'referents', 'fk' => 'reporter_id'],
        'supervisor' => ['table' => 'users', 'fk' => 'supervisor_id'],
    ];

    /**
     * `quotes`' own columns, projected verbatim, keyed by the public column
     * id -> the real `quotes` column it reads.
     *
     * @var array<string, string>
     */
    private const array QUOTE_SCALAR_COLUMNS = [
        'code' => 'code',
        'title' => 'title',
        'quote_date' => 'created_at',
        'revenue_net' => 'revenue_net',
        'revenue_vat' => 'revenue_vat',
    ];

    /** The QUOTE_SCALAR_COLUMNS honoured by the global quick-search (spec 0009). */
    private const array SEARCHABLE_QUOTE_COLUMNS = ['code', 'title'];

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $quoteColumn = self::QUOTE_SCALAR_COLUMNS[$columnId] ?? null;

        if ($quoteColumn !== null) {
            $query->whereHas('quote', function (Builder $quoteQuery) use ($quoteColumn, $columnConfig, $filter): void {
                $this->filterApplier->apply($quoteQuery, $quoteColumn, $columnConfig, $filter);
            });

            return true;
        }

        $path = self::RELATION_PATHS[$columnId] ?? null;

        if ($path === null) {
            return false;
        }

        $values = $this->setFilterValues($filter);

        if ($values !== []) {
            $query->whereHas($path, static function (Builder $relatedQuery) use ($values): void {
                $relatedQuery->whereIn(self::LABEL_COLUMN, $values);
            });
        }

        return true;
    }

    /**
     * ORDER BY the derived value via a correlated subquery — never a
     * row-multiplying JOIN on the main query.
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
        if (array_key_exists($columnId, self::QUOTE_SCALAR_COLUMNS)) {
            return $this->quoteScalarSubquery(self::QUOTE_SCALAR_COLUMNS[$columnId]);
        }

        if ($columnId === 'registry') {
            return $this->registrySubquery();
        }

        $ownFk = self::OWN_FK_RELATIONS[$columnId] ?? null;

        if ($ownFk !== null) {
            return $this->ownFkSubquery($ownFk['table'], $ownFk['fk']);
        }

        $quoteFk = self::QUOTE_RELATION_FKS[$columnId] ?? null;

        return $quoteFk === null ? null : $this->quoteRelationSubquery($quoteFk['table'], $quoteFk['fk']);
    }

    private function ownFkSubquery(string $table, string $fk): QueryBuilder
    {
        return DB::table($table)
            ->select(self::LABEL_COLUMN)
            ->whereColumn("{$table}.id", "contracts.{$fk}")
            ->limit(1);
    }

    private function quoteRelationSubquery(string $table, string $fk): QueryBuilder
    {
        return DB::table($table)
            ->select("{$table}.".self::LABEL_COLUMN)
            ->join('quotes', "quotes.{$fk}", '=', "{$table}.id")
            ->whereColumn('quotes.id', 'contracts.quote_id')
            ->limit(1);
    }

    private function registrySubquery(): QueryBuilder
    {
        return DB::table('registries')
            ->select('registries.'.self::LABEL_COLUMN)
            ->join('opportunities', 'opportunities.registry_id', '=', 'registries.id')
            ->join('quotes', 'quotes.opportunity_id', '=', 'opportunities.id')
            ->whereColumn('quotes.id', 'contracts.quote_id')
            ->limit(1);
    }

    private function quoteScalarSubquery(string $column): QueryBuilder
    {
        return DB::table('quotes')
            ->select($column)
            ->whereColumn('quotes.id', 'contracts.quote_id')
            ->limit(1);
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the 6 RELATION_PATHS
     * columns — the QUOTE_SCALAR_COLUMNS all declare `hasFilterValues: false`
     * (ContractColumnCatalog), so TableService never calls this for them.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        [$table, $ids] = $this->idsFor($columnId, $query);

        if ($table === null || $ids === null) {
            return null;
        }

        return DB::table($table)
            ->whereIn('id', $ids)
            ->when($search !== null && $search !== '', function (QueryBuilder $builder) use ($search): void {
                $builder->where(self::LABEL_COLUMN, 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy(self::LABEL_COLUMN)
            ->limit($limit)
            ->pluck(self::LABEL_COLUMN)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  Builder<Model>  $query
     * @return array{0: string|null, 1: QueryBuilder|null}
     */
    private function idsFor(string $columnId, Builder $query): array
    {
        $ownFk = self::OWN_FK_RELATIONS[$columnId] ?? null;

        if ($ownFk !== null) {
            return [$ownFk['table'], (clone $query)->whereNotNull($ownFk['fk'])->select($ownFk['fk'])->toBase()];
        }

        if ($columnId === 'registry') {
            return ['registries', $this->registryIds($query)];
        }

        $quoteFk = self::QUOTE_RELATION_FKS[$columnId] ?? null;

        if ($quoteFk !== null) {
            return [$quoteFk['table'], $this->quoteFkIds($query, $quoteFk['fk'])];
        }

        return [null, null];
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function quoteIds(Builder $query): QueryBuilder
    {
        return (clone $query)->whereNotNull('quote_id')->select('quote_id')->toBase();
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function quoteFkIds(Builder $query, string $fk): QueryBuilder
    {
        return DB::table('quotes')->whereIn('id', $this->quoteIds($query))->whereNotNull($fk)->select($fk);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function registryIds(Builder $query): QueryBuilder
    {
        $opportunityIds = DB::table('quotes')
            ->whereIn('id', $this->quoteIds($query))
            ->whereNotNull('opportunity_id')
            ->select('opportunity_id');

        return DB::table('opportunities')->whereIn('id', $opportunityIds)->whereNotNull('registry_id')->select('registry_id');
    }

    /**
     * Derived quick-search (spec 0009): `code`/`title` match the related
     * quote's own column, bound LIKE, combined into the caller's OR-group via
     * `orWhereHas`. Any other column id is not owned here (returns false).
     *
     * @param  Builder<Model>  $query
     */
    public function applySearch(Builder $query, string $columnId, string $pattern): bool
    {
        if (! in_array($columnId, self::SEARCHABLE_QUOTE_COLUMNS, true)) {
            return false;
        }

        $quoteColumn = self::QUOTE_SCALAR_COLUMNS[$columnId];

        $query->orWhereHas('quote', function (Builder $quoteQuery) use ($quoteColumn, $pattern): void {
            $quoteQuery->where($quoteColumn, 'like', $pattern);
        });

        return true;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function setFilterValues(array $filter): array
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
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
