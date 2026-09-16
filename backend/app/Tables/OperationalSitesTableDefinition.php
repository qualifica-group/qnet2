<?php

namespace App\Tables;

use App\Models\OperationalSite;
use App\Models\User;
use App\Tables\OperationalSites\OperationalSiteColumnCatalog;
use App\Tables\OperationalSites\OperationalSiteGeoColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `operational-sites` domain (spec 0011).
 *
 * The site table itself carries no real column beyond id/created_at/
 * updated_at: every other column (city/street/postal_code/province/region)
 * is DERIVED from the site's primary address — resolved by the
 * OperationalSiteGeoColumns collaborator, mirroring CompaniesTableDefinition
 * + CompanyAddressColumns. `city`/`street` are ALSO the domain's quick-search
 * columns (spec 0009/0011), delegated to the collaborator via the
 * `applyDerivedSearch` hook (spec 0011's sole generic-framework addition).
 */
class OperationalSitesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly OperationalSiteGeoColumns $geoColumns) {}

    public function domain(): string
    {
        return 'operational-sites';
    }

    /**
     * @return class-string<OperationalSite>
     */
    public function modelClass(): string
    {
        return OperationalSite::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives OperationalSitePolicy::
    // viewAny from modelClass() (operational-sites.viewAny).

    /**
     * @return Builder<OperationalSite>
     */
    public function baseQuery(): Builder
    {
        // Eager-load ONLY the primary address (+ its geo relations), so
        // mapRow reads every derived column entirely from memory — a fixed
        // number of queries regardless of row count.
        return OperationalSite::query()->with([
            'addresses' => function ($query): void {
                $query->where('is_primary', true)
                    ->with([
                        'state:id,name',
                        'province:id,name',
                        'city:id,name',
                    ]);
            },
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return OperationalSiteColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return OperationalSiteColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return OperationalSiteColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Dynamic geo set-filter options (spec 0004/0005), delegated to the
     * collaborator. `street`/`postal_code` have no options (hasFilterValues=false).
     *
     * @return array<int, scalar>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        return $this->geoColumns->isGeoColumn($columnId)
            ? $this->geoColumns->options($columnId)
            : null;
    }

    /**
     * Map a site to the row payload — entirely derived from its primary
     * address, except `id`/`created_at`. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var OperationalSite $row */
        $address = $row->addresses->first();

        return [
            'id' => $row->id,
            'alias' => $row->alias,
            'city' => $address?->city?->localizedName(),
            'street' => $address?->line1,
            'postal_code' => $address?->postal_code,
            'province' => $address?->province?->localizedName(),
            'region' => $address?->state?->localizedName(),
            'is_active' => $row->is_active,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, computed via OperationalSitePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Handle the derived filters (no real DB column) via
     * OperationalSiteGeoColumns: the 3 geo set filters and the `street`/
     * `postal_code` text filters.
     *
     * @param  Builder<OperationalSite>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($this->geoColumns->isGeoColumn($columnId)) {
            $this->geoColumns->applyGeoFilter($query, $columnId, $filter);

            return true;
        }

        if ($this->geoColumns->isTextColumn($columnId)) {
            $this->geoColumns->applyTextFilter($query, $columnId, $filter);

            return true;
        }

        return false;
    }

    /**
     * ORDER BY a derived (address-backed) column via a correlated subquery,
     * so sorting never needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<OperationalSite>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($this->geoColumns->isGeoColumn($columnId)) {
            $query->orderBy($this->geoColumns->sortSubquery($columnId), $direction);

            return true;
        }

        if ($this->geoColumns->isTextColumn($columnId)) {
            $query->orderBy($this->geoColumns->textSortSubquery($columnId), $direction);

            return true;
        }

        return false;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the 3 geo columns.
     * `street`/`postal_code` declare `hasFilterValues=false`, so TableService
     * never calls this method for them.
     *
     * @param  Builder<OperationalSite>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->geoColumns->isGeoColumn($columnId)
            ? $this->geoColumns->distinctValues($columnId, $search, $limit)
            : null;
    }

    /**
     * Derived quick-search (spec 0009/0011) for `city`/`street`, delegated to
     * the collaborator. Any other searchable column would fall through to the
     * generic engine (not applicable today — every OTHER column here is
     * either not searchable or a real column).
     *
     * @param  Builder<OperationalSite>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->geoColumns->applySearch($query, $columnId, $pattern);
    }
}
