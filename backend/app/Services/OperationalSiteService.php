<?php

namespace App\Services;

use App\DataObjects\OperationalSites\CreateOperationalSiteData;
use App\DataObjects\OperationalSites\UpdateOperationalSiteData;
use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Address;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `operational-sites` resource (spec 0011): create/
 * update/delete, including the single-address invariant delegated to
 * AddressService (createFor on the first write, update thereafter — never a
 * second row), mirroring CompanyService.
 *
 * Unlike CompanyService's all-or-nothing nested `address` object, the write
 * payload here is FLAT and each field is independently `sometimes` (AC-011):
 * update() merges only the SUBMITTED fields onto the site's current primary
 * address (unsubmitted fields keep their persisted value), then calls
 * AddressService::update with the fully-merged CreateAddress — a full
 * overwrite is what AddressService expects, so the merge (not a partial
 * Eloquent update) is what makes the PATCH semantics truly partial.
 */
class OperationalSiteService
{
    /**
     * Relations eager-loaded on every returned model, so OperationalSiteResource
     * never N+1s while hydrating the address' geo names.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = ['addresses.country', 'addresses.state', 'addresses.province', 'addresses.city'];

    public function __construct(private readonly AddressService $addresses) {}

    public function create(User $actor, CreateOperationalSiteData $data): OperationalSite
    {
        return DB::transaction(function () use ($data): OperationalSite {
            /** @var OperationalSite $site */
            $site = OperationalSite::create(['alias' => $data->alias, 'is_active' => $data->isActive]);

            $this->addresses->createFor($site, $data->toAddress());

            return $site->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function update(User $actor, OperationalSite $site, UpdateOperationalSiteData $data): OperationalSite
    {
        return DB::transaction(function () use ($site, $data): OperationalSite {
            // Save unconditionally so the custom-field pipeline (HasCustomFields
            // `saved` hook) fires even for a custom-fields-only edit; a clean save
            // is a no-op for the native path (no dirty attrs => no query, no
            // updated_at, no log).
            if ($data->aliasSubmitted) {
                $site->alias = $data->alias;
            }
            if ($data->isActive !== null) {
                $site->is_active = $data->isActive;
            }
            $site->save();

            if ($data->hasAddressChanges()) {
                $this->writeAddress($site, $data);
            }

            return $site->fresh(self::HYDRATED_RELATIONS);
        });
    }

    /**
     * Restrictive delete (spec 0024 BR-2/D-4): a site referenced by at least
     * one lead cannot be removed. Otherwise its owned address cascades away
     * (HasAddresses::bootHasAddresses).
     */
    public function delete(OperationalSite $site): void
    {
        if ($site->leads()->exists()) {
            abort(409, 'This operational site has leads and cannot be deleted.');
        }

        $site->delete();
    }

    /**
     * Minimal, searchable, paginated operational-site list for the for-select
     * standard (spec 0015, ADR 0011), mirroring UserService::forSelect. A site
     * has no own name column (identity = its address, mirroring
     * OperationalSitesTableDefinition/OperationalSiteGeoColumns): search and
     * order both read the PRIMARY address' `line1`/city name, ids[] hydrated
     * without inflating total. $businessFunctionId (spec 0040 BR-4), when
     * given, restricts the list to sites linked to that function via the
     * `business_function_operational_site` pivot.
     *
     * Only active sites are offered (spec 0135); an inactive one still
     * hydrates through ids[] so a record keeps showing its current site.
     */
    public function forSelect(ForSelectQuery $query, ?int $businessFunctionId = null): ForSelectResult
    {
        $base = $this->forSelectBaseQuery()->where('is_active', true);

        if ($businessFunctionId !== null) {
            $base->whereHas('businessFunctions', function (Builder $functionQuery) use ($businessFunctionId): void {
                $functionQuery->whereKey($businessFunctionId);
            });
        }

        if ($query->hasSearch()) {
            $term = '%'.$query->search.'%';
            $base->whereHas('addresses', function (Builder $addressQuery) use ($term): void {
                $addressQuery->where('is_primary', true)
                    ->where(function (Builder $inner) use ($term): void {
                        $inner->where('line1', 'like', $term)
                            ->orWhereHas('city', function (Builder $cityQuery) use ($term): void {
                                $cityQuery->where('name', 'like', $term);
                            });
                    });
            });
        }

        $total = (clone $base)->count();

        /** @var Collection<int, OperationalSite> $page */
        $page = $base->orderBy($this->primaryLine1Subquery())
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Base for-select query: sites with their primary address (+ city name)
     * eager-loaded, so OperationalSiteForSelectResource never N+1s.
     *
     * @return Builder<OperationalSite>
     */
    private function forSelectBaseQuery(): Builder
    {
        // The eager-load callback receives the Relation instance (not a
        // Builder), so it is intentionally untyped — mirroring
        // OperationalSitesTableDefinition::baseQuery.
        return OperationalSite::query()->with([
            'addresses' => function ($addressQuery): void {
                $addressQuery->where('is_primary', true)->with('city:id,name');
            },
        ]);
    }

    /**
     * Correlated subquery selecting the site's primary address `line1`, used
     * as the ORDER BY key (no own column to sort on), mirroring
     * OperationalSiteGeoColumns::textSortSubquery.
     *
     * @return Builder<Address>
     */
    private function primaryLine1Subquery(): Builder
    {
        return Address::query()
            ->select('line1')
            ->whereColumn('addresses.addressable_id', 'operational_sites.id')
            ->where('addresses.addressable_type', (new OperationalSite)->getMorphClass())
            ->where('addresses.is_primary', true)
            ->limit(1);
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are not
     * already on the page, deduplicated. They bypass search AND the
     * `is_active` filter, and the same primary-address projection applies.
     * Total is unaffected.
     *
     * @param  Collection<int, OperationalSite>  $page
     * @return Collection<int, OperationalSite>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, OperationalSite> $hydrated */
        $hydrated = $this->forSelectBaseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy($this->primaryLine1Subquery())
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * Merge the SUBMITTED fields onto the site's current primary address
     * (falling back to its persisted value for anything not submitted), then
     * write it: update the existing row when there is one, else create it.
     */
    private function writeAddress(OperationalSite $site, UpdateOperationalSiteData $data): void
    {
        $existing = $site->addresses()->first();
        $merged = $this->mergeAddress($data, $existing);

        if ($existing !== null) {
            $this->addresses->update($existing, $merged);

            return;
        }

        $this->addresses->createFor($site, $merged);
    }

    /**
     * Build the FULL CreateAddress AddressService expects, from the submitted
     * fields layered onto $current's persisted values (or empty defaults when
     * there is no address yet). `isPrimary` is always preserved as the
     * existing value (defaulting to true for a brand-new address, since it
     * will be the site's only one) — never reset to CreateAddress' own
     * `false` default, which would silently demote the site's sole address.
     */
    private function mergeAddress(UpdateOperationalSiteData $data, ?Address $current): CreateAddress
    {
        return new CreateAddress(
            line1: $data->line1Submitted ? (string) $data->line1 : (string) ($current?->line1 ?? ''),
            postalCode: $data->postalCodeSubmitted ? $data->postalCode : $current?->postal_code,
            cityId: $data->cityIdSubmitted ? $data->cityId : $current?->city_id,
            provinceId: $data->provinceIdSubmitted ? $data->provinceId : $current?->province_id,
            stateId: $data->stateIdSubmitted ? $data->stateId : $current?->state_id,
            countryId: $data->countryIdSubmitted ? $data->countryId : $current?->country_id,
            isPrimary: $current?->is_primary ?? true,
        );
    }
}
