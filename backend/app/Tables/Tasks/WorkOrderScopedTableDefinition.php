<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Models\User;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Decorator that scopes the `tasks` domain to a single Work Order (spec 0133,
 * D-1): the Commessa detail's "Task" tab, filtered to that work order's own
 * tasks. `tasks.work_order_id` is a real FK, so a single `where()` in
 * `baseQuery()` is the entire scope, ANDed with the `TaskVisibilityScope` the
 * inner definition already applies — the tab never shows a task the actor
 * could not see on the Task page. Ricalca 1:1
 * `App\Tables\WorkOrders\QuoteScopedTableDefinition` (spec 0095): every other
 * method is IDENTICAL scoped or not, hence pure passthrough to $inner.
 *
 * Pure passthrough when no scope has been set: the standalone Task list page
 * is byte-identical to today.
 */
class WorkOrderScopedTableDefinition implements TableDefinition
{
    use DelegatesUnaugmentedTableMethods;

    private ?int $workOrderScope = null;

    public function __construct(private readonly TableDefinition $inner) {}

    /**
     * Narrows `baseQuery()` to one Work Order's own tasks (null = no scope,
     * the Task list page's own unscoped behavior).
     */
    public function scopeToWorkOrder(?int $workOrderId): void
    {
        $this->workOrderScope = $workOrderId;
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->workOrderScope === null) {
            return $query;
        }

        return $query->where('tasks.work_order_id', $this->workOrderScope);
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
