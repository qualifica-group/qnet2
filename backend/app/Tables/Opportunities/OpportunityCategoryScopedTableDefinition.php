<?php

declare(strict_types=1);

namespace App\Tables\Opportunities;

use App\Models\User;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\ProductCategoryScopable;
use App\Tables\RegistryScopable;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Decorator that scopes the `opportunities` domain to one product category
 * (spec 0335, D-2): an Opportunity shows in the tab of every category of its
 * `opportunity_product_lines`. It is the OUTERMOST decorator of the domain,
 * so it also implements `RegistryScopable` and forwards the registry scope to
 * the inner `RegistryScopedTableDefinition`: callers test `instanceof`, and
 * both interfaces must be seen on the same object.
 *
 * Only the row set changes: columns, config and layout are identical with or
 * without a category (D-3), hence pure passthrough for everything else.
 */
class OpportunityCategoryScopedTableDefinition implements ProductCategoryScopable, RegistryScopable, TableDefinition
{
    use DelegatesUnaugmentedTableMethods;

    private ?int $categoryScope = null;

    public function __construct(private readonly TableDefinition&RegistryScopable $inner) {}

    public function scopeToProductCategory(?int $productCategoryId): void
    {
        $this->categoryScope = $productCategoryId;
    }

    public function scopeToRegistry(?int $registryId): void
    {
        $this->inner->scopeToRegistry($registryId);
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->categoryScope === null) {
            return $query;
        }

        $categoryScope = $this->categoryScope;

        // IN-subquery rather than whereHas: starts from the category's own index.
        return $query->whereIn(
            $query->qualifyColumn('id'),
            static fn (QueryBuilder $lines) => $lines
                ->select('opportunity_id')
                ->from('opportunity_product_lines')
                ->where('product_category_id', $categoryScope),
        );
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
