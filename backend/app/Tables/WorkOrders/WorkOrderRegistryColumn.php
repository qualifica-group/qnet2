<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The `registry` (Anagrafica) column of the `work-orders` grid: a work order
 * has no `registry_id` of its own, so the client is reached through
 * `work_orders.quote_id` -> `quotes.opportunity_id` ->
 * `opportunities.registry_id` — the same chain WorkOrderResource and the
 * Contracts grid (ContractRelationColumns) already project. Read-only: the
 * form never edits it, it follows the linked quote. Every hop is NOT NULL, so
 * a work order always has a registry and the set filter has no blank entry.
 *
 * A `set` filter on `registries.name` via `whereHas` with BOUND values, a
 * correlated-subquery sort (never a row-multiplying JOIN) and Excel-like
 * distinct values scoped by every other active filter (spec 0004/0005).
 * Split out of WorkOrderDerivedColumns (file-size split, engineering.md §6).
 */
final class WorkOrderRegistryColumn
{
    public const string COLUMN = 'registry';

    private const string RELATION_PATH = 'quote.opportunity.registry';

    private const string LABEL_COLUMN = 'name';

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $values = $this->setFilterValues($filter);

        if ($values === []) {
            return;
        }

        $query->whereHas(self::RELATION_PATH, static function (Builder $registryQuery) use ($values): void {
            $registryQuery->whereIn(self::LABEL_COLUMN, $values);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy(
            DB::table('registries')
                ->select('registries.'.self::LABEL_COLUMN)
                ->join('opportunities', 'opportunities.registry_id', '=', 'registries.id')
                ->join('quotes', 'quotes.opportunity_id', '=', 'opportunities.id')
                ->whereColumn('quotes.id', 'work_orders.quote_id')
                ->limit(1),
            $direction,
        );
    }

    /**
     * Distinct registry names among the work orders matching $query.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(?string $search, Builder $query, int $limit): array
    {
        return DB::table('registries')
            ->whereIn('id', $this->registryIds($query))
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
     */
    private function registryIds(Builder $query): QueryBuilder
    {
        $quoteIds = (clone $query)->select('quote_id')->toBase();
        $opportunityIds = DB::table('quotes')->whereIn('id', $quoteIds)->select('opportunity_id');

        return DB::table('opportunities')->whereIn('id', $opportunityIds)->select('registry_id');
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

        return array_values(array_filter($values, static fn ($value): bool => is_string($value) && $value !== ''));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
