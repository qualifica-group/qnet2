<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Models\User;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Decorator that scopes the `work-orders` domain to a single Quote (spec
 * 0095, D-8): the Contratto detail's "Commesse" tab, filtered to the
 * offer's own work orders. `work_orders.quote_id` is a REAL, NOT NULL,
 * indexed column (D-5 of spec 0093), so a single `where()` in `baseQuery()`
 * is the entire scope — the scope is `quoteId`, not `contractId`, because
 * Contratto and Offerta are 1:1 (`contracts.quote_id` UNIQUE) and
 * `work_orders.quote_id` already exists, so filtering by it needs no join.
 * Ricalca 1:1 `App\Tables\Quotes\OpportunityScopedTableDefinition` (spec
 * 0067) — every other method (columns/filters/actions/sort-filter-search
 * allow-lists/resolveConfig/defaultColumnLayout) is IDENTICAL scoped or
 * not, hence pure passthrough to $inner.
 *
 * Pure passthrough when no scope has been set (rows()/values()/columns()
 * without `quoteId`): every existing caller of the `work-orders` domain
 * (the standalone Commesse list page) is byte-identical to today (AC-054).
 *
 * Composed OUTSIDE `CustomFieldAwareTableDefinition` in
 * `TableRegistry::resolve()` (`work-orders` is custom-fieldable): `$inner`
 * is already the custom-field-augmented definition, so `custom.*` columns
 * work unchanged inside a Quote-scoped grid too.
 */
class QuoteScopedTableDefinition implements TableDefinition
{
    use DelegatesUnaugmentedTableMethods;

    private ?int $quoteScope = null;

    public function __construct(private readonly TableDefinition $inner) {}

    /**
     * Narrows `baseQuery()` to one Quote's own work orders (null = no scope,
     * the "Commesse" list page's own unscoped behavior).
     */
    public function scopeToQuote(?int $quoteId): void
    {
        $this->quoteScope = $quoteId;
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->quoteScope === null) {
            return $query;
        }

        return $query->where('work_orders.quote_id', $this->quoteScope);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return $this->inner->columns();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        return $this->inner->mapRow($actor, $row);
    }

    public function sortableColumnIds(): array
    {
        return $this->inner->sortableColumnIds();
    }

    public function filterableColumnIds(): array
    {
        return $this->inner->filterableColumnIds();
    }

    public function searchableColumnIds(): array
    {
        return $this->inner->searchableColumnIds();
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array
    {
        return $this->inner->resolveConfig($actor);
    }

    /**
     * @return array<string, array{visible: bool, width: int|null, order: int}>
     */
    public function defaultColumnLayout(): array
    {
        return $this->inner->defaultColumnLayout();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array
    {
        return $this->inner->filterableColumnMap();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $this->inner->applyDerivedFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return $this->inner->applyDerivedSort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->inner->distinctValues($actor, $columnId, $columnConfig, $search, $query, $limit);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->inner->applyDerivedSearch($query, $columnId, $pattern);
    }
}
