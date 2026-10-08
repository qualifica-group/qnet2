<?php

declare(strict_types=1);

namespace App\Tables\Quotes;

use App\Models\User;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\RegistryScopable;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Decorator that scopes the `quotes` domain to a single Opportunity (spec
 * 0067, D-1): the second concrete case of table row-scoping in the codebase,
 * after `App\Tables\RequestManagement\AttributeScopedTableDefinition` — but,
 * unlike that one, `quotes.opportunity_id` is a REAL, NOT NULL, indexed
 * column, so a single `where()` in `baseQuery()` is the entire scope. Every
 * other method (columns/filters/actions/sort-filter-search allow-lists/
 * resolveConfig/defaultColumnLayout) is IDENTICAL scoped or not, hence pure
 * passthrough to $inner: no `scopeToAllOpportunities()` twin is needed, and
 * — unlike `AttributeScopedTableDefinition` — the column-preferences/filter
 * persistence endpoints (`TableController::savePreferences()`/
 * `saveFilters()`) never call the setter and stay entirely unaware this
 * decorator exists (D-4: preferences/filters are shared, not per-Opportunity).
 *
 * Pure passthrough when no scope has been set (rows()/values()/columns()
 * without `opportunityId`): every existing caller of the `quotes` domain
 * (the standalone Offerte list page) is byte-identical to today (AC-002).
 *
 * Composed OUTSIDE `CustomFieldAwareTableDefinition` in
 * `TableRegistry::resolve()` (quotes is custom-fieldable): `$inner` is
 * already the custom-field-augmented definition, so `custom.*` columns work
 * unchanged inside an Opportunity-scoped grid too.
 *
 * Also the `RegistryScopable` of `quotes` (spec 0199): the client of an
 * Offerta is its Opportunity's, so the scope is a `whereIn` on the client's
 * opportunities, in AND with the Opportunity scope. This is the outermost
 * decorator of the domain, hence the one `instanceof RegistryScopable` sees.
 */
class OpportunityScopedTableDefinition implements RegistryScopable, TableDefinition
{
    use DelegatesUnaugmentedTableMethods;

    private ?int $opportunityScope = null;

    private ?int $registryScope = null;

    public function __construct(private readonly TableDefinition $inner) {}

    /**
     * Narrows `baseQuery()` to one Opportunity's Offerte (null = no scope,
     * the "Offerte" list page's own unscoped behavior).
     */
    public function scopeToOpportunity(?int $opportunityId): void
    {
        $this->opportunityScope = $opportunityId;
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->opportunityScope !== null) {
            $query->where('quotes.opportunity_id', $this->opportunityScope);
        }

        if ($this->registryScope !== null) {
            $query->whereIn(
                'quotes.opportunity_id',
                DB::table('opportunities')->select('id')->where('registry_id', $this->registryScope),
            );
        }

        return $query;
    }

    /**
     * Narrows `baseQuery()` to the Offerte of one client's Opportunities
     * (null = no scope).
     */
    public function scopeToRegistry(?int $registryId): void
    {
        $this->registryScope = $registryId;
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
