<?php

namespace App\Tables;

use App\Models\Company;
use App\Models\User;
use App\Tables\Companies\CompanyAddressColumns;
use App\Tables\Companies\CompanyColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `companies` domain (spec 0010).
 *
 * Real columns (id, denomination, vat_number, created_at) are handled
 * entirely by the generic engine. The 5 address-derived columns (country/
 * region/province/city/postal_code) have no real DB column of their own —
 * resolved from the company's primary address by the CompanyAddressColumns
 * collaborator, mirroring UsersTableDefinition + UserGeoColumns.
 */
class CompaniesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly CompanyAddressColumns $addressColumns) {}

    public function domain(): string
    {
        return 'companies';
    }

    /**
     * @return class-string<Company>
     */
    public function modelClass(): string
    {
        return Company::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives CompanyPolicy::viewAny from
    // modelClass() (companies.viewAny).

    /**
     * @return Builder<Company>
     */
    public function baseQuery(): Builder
    {
        // Eager-load ONLY the primary address (+ its geo relations), so
        // mapRow reads the derived columns entirely from memory — a fixed
        // number of queries regardless of row count.
        return Company::query()->with([
            'addresses' => function ($query): void {
                $query->where('is_primary', true)
                    ->with([
                        'country:id,name',
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
        return CompanyColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return CompanyColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CompanyColumnCatalog::actions();
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
     * collaborator. `postal_code` has no options (hasFilterValues=false).
     *
     * @return array<int, scalar>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        return $this->addressColumns->isGeoColumn($columnId)
            ? $this->addressColumns->options($columnId)
            : null;
    }

    /**
     * Map a Company to the row payload (real fields + the primary address'
     * derived geo/postal_code fields). `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Company $row */
        $address = $row->addresses->first();

        return [
            'id' => $row->id,
            'denomination' => $row->denomination,
            'vat_number' => $row->vat_number,
            'city' => $address?->city?->localizedName(),
            'province' => $address?->province?->localizedName(),
            'region' => $address?->state?->localizedName(),
            'postal_code' => $address?->postal_code,
            'country' => $address?->country?->localizedName(),
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, computed via CompanyPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
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
     * Handle the derived filters (no real DB column) via CompanyAddressColumns:
     * the 4 geo set filters and the `postal_code` text filter.
     *
     * @param  Builder<Company>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($this->addressColumns->isGeoColumn($columnId)) {
            $this->addressColumns->applyFilter($query, $columnId, $filter);

            return true;
        }

        if ($this->addressColumns->isPostalCodeColumn($columnId)) {
            $this->addressColumns->applyPostalCodeFilter($query, $filter);

            return true;
        }

        return false;
    }

    /**
     * ORDER BY a derived (address-backed) column via a correlated subquery, so
     * sorting never needs a row-multiplying JOIN on the main companies query.
     *
     * @param  Builder<Company>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($this->addressColumns->isGeoColumn($columnId)) {
            $query->orderBy($this->addressColumns->sortSubquery($columnId), $direction);

            return true;
        }

        if ($this->addressColumns->isPostalCodeColumn($columnId)) {
            $query->orderBy($this->addressColumns->postalCodeSortSubquery(), $direction);

            return true;
        }

        return false;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the 4 geo columns.
     * `postal_code` declares `hasFilterValues=false`, so TableService never
     * calls this method for it. Every other (real-column) filterable column
     * falls through to the generic engine's `SELECT DISTINCT` (return null).
     *
     * @param  Builder<Company>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->addressColumns->isGeoColumn($columnId)
            ? $this->addressColumns->distinctValues($columnId, $search, $limit)
            : null;
    }
}
