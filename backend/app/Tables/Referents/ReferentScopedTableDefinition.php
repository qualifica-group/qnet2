<?php

declare(strict_types=1);

namespace App\Tables\Referents;

use App\Models\Referent;
use App\Models\User;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\ReferentScopable;
use App\Tables\RegistryScopable;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Decorator that scopes a domain to the rows whose polymorphic column points
 * at one Referente (spec 0336): the recipient of `commission-configurations`,
 * its morph type pinned to the Referent alias so an anagrafica or a user with
 * the same id never matches. It is the OUTERMOST decorator of the domain, so it
 * also implements `RegistryScopable` and forwards the registry scope to the
 * inner `RegistryScopedTableDefinition` (spec 0204): callers test `instanceof`,
 * and both interfaces must be seen on the same object.
 *
 * Pure passthrough when no scope has been set: only the row set changes.
 */
class ReferentScopedTableDefinition implements ReferentScopable, RegistryScopable, TableDefinition
{
    use DelegatesUnaugmentedTableMethods;

    private ?int $referentScope = null;

    public function __construct(
        private readonly TableDefinition&RegistryScopable $inner,
        private readonly string $referentColumn,
        private readonly string $morphTypeColumn,
    ) {}

    public function scopeToReferent(?int $referentId): void
    {
        $this->referentScope = $referentId;
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

        if ($this->referentScope === null) {
            return $query;
        }

        return $query
            ->where($this->referentColumn, $this->referentScope)
            ->where($this->morphTypeColumn, (new Referent)->getMorphClass());
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
