<?php

namespace App\Tables;

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\User;
use App\Tables\CompanySites\CompanySiteAddressColumns;
use App\Tables\CompanySites\CompanySiteColumnCatalog;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `company-sites` domain (spec 0020).
 *
 * Real columns (id, is_default, name, created_at) are handled entirely by the
 * generic engine. `primary_contact` is COMPUTED from the card's eager-loaded
 * contacts via the shared PrimaryContactColumn (display-only, like the Registry
 * grid). The 4 address-derived columns (city/region/province/postal_code) have
 * no real DB column of their own — resolved from the CARD's primary address
 * (CompanySite → personalData → addresses) by the CompanySiteAddressColumns
 * collaborator. `company` has no real DB column of its own either — it is the
 * owning Company's denomination (`company_id` belongsTo), handled directly
 * here (mirrors ReferentsTableDefinition's `referent_type`).
 */
class CompanySitesTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in the `company` set filter. Caps the
     * WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(
        private readonly CompanySiteAddressColumns $addressColumns,
        private readonly PrimaryContactColumn $contactColumn,
    ) {}

    public function domain(): string
    {
        return 'company-sites';
    }

    /**
     * @return class-string<CompanySite>
     */
    public function modelClass(): string
    {
        return CompanySite::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives CompanySitePolicy::viewAny
    // from modelClass() (company-sites.viewAny).

    /**
     * @return Builder<CompanySite>
     */
    public function baseQuery(): Builder
    {
        // Eager-load the card's contacts and ONLY its primary address (+ geo
        // relations), plus the logo, so mapRow reads every derived field
        // entirely from memory — a fixed number of queries regardless of row
        // count.
        return CompanySite::query()->with([
            'personalData.contacts',
            'personalData.addresses' => function ($query): void {
                $query->where('is_primary', true)
                    ->with(['state:id,name', 'province:id,name', 'city:id,name']);
            },
            'logo',
            'company',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return CompanySiteColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return CompanySiteColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CompanySiteColumnCatalog::actions();
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
     * collaborator.
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
     * Map a CompanySite to the row payload (real fields + the card's primary
     * contacts + the card's primary address' derived geo/postal_code fields +
     * the logo). `actions` is attached by the generic TableService via
     * actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var CompanySite $row */
        $card = $row->personalData;
        $address = $card?->addresses->first();

        return [
            'id' => $row->id,
            'is_default' => $row->is_default,
            'name' => $row->name,
            'company' => $row->company !== null
                ? ['id' => $row->company->id, 'name' => $row->company->denomination]
                : null,
            'primary_contact' => $this->contactColumn->format($card?->contacts),
            'city' => $address?->city?->localizedName(),
            'province' => $address?->province?->localizedName(),
            'region' => $address?->state?->localizedName(),
            'postal_code' => $address?->postal_code,
            'created_at' => $row->created_at,
            'logo_url' => $row->logoDataUri(),
        ];
    }

    /**
     * Allowed action keys for a single row, computed via CompanySitePolicy.
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
     * Handle the derived filters (no real DB column): the `company` set
     * filter (whereHas by denomination), and the address-backed ones via
     * CompanySiteAddressColumns (the 3 geo set filters and the `postal_code`
     * text filter).
     *
     * @param  Builder<CompanySite>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === 'company') {
            return $this->filterCompany($query, $filter);
        }

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
     * Derived `company` set filter via whereHas on the related company's
     * denomination. Only string names, capped cardinality, bound parameters.
     *
     * @param  Builder<CompanySite>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterCompany(Builder $query, array $filter): bool
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($names === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(static function (Builder $group) use ($names, $matchesBlank): void {
            if ($names !== []) {
                $group->whereHas('company', static function (Builder $relatedQuery) use ($names): void {
                    $relatedQuery->whereIn('denomination', $names);
                });
            }

            // The blank entry ("(Vuoti)"): the sites owned by no company.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('company');
            }
        });

        return true;
    }

    /**
     * ORDER BY a derived column via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main company_sites query: the owning
     * company's denomination (`company`) or an address-backed column via
     * CompanySiteAddressColumns.
     *
     * @param  Builder<CompanySite>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === 'company') {
            $subquery = Company::query()
                ->select('denomination')
                ->whereColumn('companies.id', 'company_sites.company_id')
                ->limit(1);

            $query->orderBy($subquery, $direction);

            return true;
        }

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
     * Excel-like distinct values (spec 0004/0005) for `company` (owning
     * companies' denominations) and the 3 geo columns. `postal_code` declares
     * `hasFilterValues=false`, so TableService never calls this method for it.
     * Every other (real-column) filterable column falls through to the
     * generic engine's `SELECT DISTINCT` (return null) — including
     * `is_default`.
     *
     * @param  Builder<CompanySite>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'company') {
            return $this->distinctCompanyNames($query, $search, $limit);
        }

        return $this->addressColumns->isGeoColumn($columnId)
            ? $this->addressColumns->distinctValues($columnId, $search, $limit)
            : null;
    }

    /**
     * Distinct company denominations among the sites matched by `$query`
     * (already narrowed by every OTHER active filter), mirrors
     * ReferentsTableDefinition::distinctReferentTypeNames.
     *
     * @param  Builder<CompanySite>  $query
     * @return array<int, string>
     */
    private function distinctCompanyNames(Builder $query, ?string $search, int $limit): array
    {
        $companyIds = (clone $query)->whereNotNull('company_id')->select('company_id');

        $values = DB::table('companies')
            ->whereIn('id', $companyIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('denomination', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('denomination')
            ->limit($limit)
            ->pluck('denomination')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave('company')
            ->exists());
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
