<?php

namespace App\Migrations\Support;

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Support\Geo\ItalianGeoLocalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves an external system's geo values (country ISO code, region,
 * province, city) to qnet's geo ids. Shared by every migration source whose records carry the
 * same address shape (CompaniesSource, OperationalSitesSource and any future
 * import) — the matching lives here ONCE, so a new source gets it for free.
 *
 * Two layers make the legacy strings resolvable against the ENGLISH reference
 * dataset (world.sql):
 *  1. ItalianGeoLocalizer translates the Italian region / province plate code
 *     to the reference spelling (`Sicilia`->`Sicily`, `NA`->`Naples`), and
 *     strips site-label noise off the `comune`. The country is an ISO code.
 *  2. The lookup itself is case-insensitive (LIKE, wildcards escaped), so a
 *     legacy `FRATTAMAGGIORE` still matches the dataset's `Frattamaggiore`.
 *
 * Resolution is BACKFILLING, not strictly top-down: a level is looked up scoped
 * to whatever ancestor already resolved, and a resolved province/city fills in
 * any ancestor that was blank. This matters because sources vary — companies
 * send an EMPTY region but a province plate code + comune; an Italian plate code
 * identifies its province (hence its region and country) nationally, so the
 * whole chain still resolves. A non-empty level that cannot resolve is a
 * non-fatal warning; whatever resolved is still used.
 */
class MigrationGeoResolver
{
    public function __construct(private readonly ItalianGeoLocalizer $localizer) {}

    public function resolve(?string $countryCode, ?string $stateName, ?string $provinceName, ?string $cityName): MigrationGeoResolution
    {
        $warnings = [];

        // Step 1: top-down where present (country -> region).
        $countryId = $this->resolveCountry($countryCode, $warnings);
        $stateId = $this->resolveState($countryId, $stateName, $warnings);

        // Step 2: province from its plate code, backfilling a blank region/country.
        [$provinceId, $stateId, $countryId] = $this->resolveProvince($provinceName, $stateId, $countryId, $warnings);

        // Step 3: city scoped to the (possibly backfilled) province/region,
        // backfilling any ancestor still blank.
        [$cityId, $stateId, $countryId] = $this->resolveCity($cityName, $provinceId, $stateId, $countryId, $warnings);

        return new MigrationGeoResolution($countryId, $stateId, $provinceId, $cityId, $warnings);
    }

    /**
     * The contract carries the ISO 3166-1 alpha-2 code (spec 0189 G-28): the
     * legacy maps its own nation labels, qnet-2 only looks the code up.
     *
     * @param  array<int, string>  $warnings
     */
    private function resolveCountry(?string $code, array &$warnings): ?int
    {
        $code = $this->blankToNull($code);

        if ($code === null) {
            return null;
        }

        $country = Country::query()->where('iso2', strtoupper($code))->first();

        if ($country === null) {
            $warnings[] = "Unresolved country '{$code}'.";

            return null;
        }

        return $country->id;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveState(?int $countryId, ?string $name, array &$warnings): ?int
    {
        $name = $this->blankToNull($name);

        if ($name === null) {
            return null;
        }

        if ($countryId === null) {
            $warnings[] = "Unresolved region '{$name}' (no resolved country).";

            return null;
        }

        $state = $this->firstByName(State::query()->where('country_id', $countryId), $this->localizer->region($name));

        if ($state === null) {
            $warnings[] = "Unresolved region '{$name}'.";

            return null;
        }

        return $state->id;
    }

    /**
     * The legacy province value is a plate code (`NA`, `RG`): translate it to a
     * province name and match it scoped to whatever ancestor resolved (region,
     * else country, else nationally — a plate code is unique in Italy). A found
     * province backfills a blank region/country from its own ancestry.
     *
     * @param  array<int, string>  $warnings
     * @return array{0: ?int, 1: ?int, 2: ?int} [provinceId, stateId, countryId]
     */
    private function resolveProvince(?string $name, ?int $stateId, ?int $countryId, array &$warnings): array
    {
        $name = $this->blankToNull($name);

        if ($name === null) {
            return [null, $stateId, $countryId];
        }

        $canonical = $this->localizer->province($name);

        $province = $canonical === null
            ? null
            : $this->firstByName($this->scopeToAncestor(Province::query(), $stateId, $countryId), $canonical);

        if ($province === null) {
            $warnings[] = "Unresolved province '{$name}'.";

            return [null, $stateId, $countryId];
        }

        return [$province->id, $stateId ?? $province->state_id, $countryId ?? $province->country_id];
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array{0: ?int, 1: ?int, 2: ?int} [cityId, stateId, countryId]
     */
    private function resolveCity(?string $name, ?int $provinceId, ?int $stateId, ?int $countryId, array &$warnings): array
    {
        $name = $this->blankToNull($name);

        if ($name === null) {
            return [null, $stateId, $countryId];
        }

        // A city name needs at least a province or region scope to be safely
        // disambiguated (many comuni share a name across Italy).
        if ($provinceId === null && $stateId === null) {
            $warnings[] = "Unresolved city '{$name}' (no resolved region).";

            return [null, $stateId, $countryId];
        }

        $canonical = $this->localizer->city($name);

        $query = City::query()->when(
            $provinceId !== null,
            fn (Builder $inner) => $inner->where('province_id', $provinceId),
            fn (Builder $inner) => $inner->where('state_id', $stateId),
        );

        $city = $canonical === null ? null : $this->firstByName($query, $canonical);

        if ($city === null) {
            $warnings[] = "Unresolved city '{$name}'.";

            return [null, $stateId, $countryId];
        }

        return [$city->id, $stateId ?? $city->state_id, $countryId ?? $city->country_id];
    }

    /**
     * Scope a province/city query to the tightest resolved ancestor (region,
     * else country, else unscoped — a plate code is nationally unique).
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    private function scopeToAncestor(Builder $query, ?int $stateId, ?int $countryId): Builder
    {
        return $query
            ->when($stateId !== null, fn (Builder $inner) => $inner->where('state_id', $stateId))
            ->when($stateId === null && $countryId !== null, fn (Builder $inner) => $inner->where('country_id', $countryId));
    }

    /**
     * Case-insensitive exact match on `name` (LIKE with the value's own
     * wildcards escaped — no pattern search, just collation-independent
     * equality). Returns the first matching row (with its ancestor ids for
     * backfilling), or null.
     *
     * @param  Builder<*>  $query
     */
    private function firstByName(Builder $query, string $name): ?Model
    {
        return $query->where('name', 'like', addcslashes($name, '\\%_'))->first();
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
