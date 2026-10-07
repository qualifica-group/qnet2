<?php

declare(strict_types=1);

namespace App\Tables\Registries;

use App\Models\Registry;
use App\Models\User;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\RegistryScopable;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Decorator that scopes a domain to a single client/Anagrafica (spec 0199)
 * for the domains that carry a REAL registry id column (`opportunities`,
 * `tasks`): a single `where()` on the fully-qualified column handed in by
 * `TableRegistry` is the entire scope. A polymorphic column (the recipient
 * of `commission-configurations`, spec 0204) also gets its morph type
 * column, pinned to the Registry alias so a referent or user with the same
 * id never matches. Every other method is IDENTICAL scoped or not, hence
 * pure passthrough to $inner.
 *
 * Pure passthrough when no scope has been set: every existing caller is
 * byte-identical to today. Composed OUTSIDE `CustomFieldAwareTableDefinition`
 * in `TableRegistry::resolve()`, so `custom.*` columns work unchanged.
 */
class RegistryScopedTableDefinition implements RegistryScopable, TableDefinition
{
    use DelegatesUnaugmentedTableMethods;

    private ?int $registryScope = null;

    public function __construct(
        private readonly TableDefinition $inner,
        private readonly string $registryColumn,
        private readonly ?string $morphTypeColumn = null,
    ) {}

    public function scopeToRegistry(?int $registryId): void
    {
        $this->registryScope = $registryId;
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->registryScope === null) {
            return $query;
        }

        $query->where($this->registryColumn, $this->registryScope);

        if ($this->morphTypeColumn !== null) {
            $query->where($this->morphTypeColumn, (new Registry)->getMorphClass());
        }

        return $query;
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
