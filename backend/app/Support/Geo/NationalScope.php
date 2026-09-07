<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Models\Country;

/**
 * National mode (config/geo.php <- DEFAULT_COUNTRY_ISO2): resolves the
 * configured country CODE into the id the parentless geo lookups filter on.
 *
 * The setting started as a UI-only prefill of the country field. It now also
 * NARROWS the only two geo reads that have no parent to bound them — the free
 * region search (`GET /api/states/for-select`) and the city-first city search
 * (`GET /api/cities?search=`) — which otherwise served the whole world
 * reference dataset, surfacing foreign regions and localities in a back office
 * that only works on one country.
 *
 * The parent-scoped cascade steps (`states?country_id`, `provinces?state_id`,
 * `cities?state_id|province_id`) are deliberately NOT filtered here: their
 * parent already bounds them, so a foreign address stays reachable by picking
 * its country explicitly — national mode narrows the shortcuts, it does not
 * remove the international path.
 *
 * Returns null — no filter, international mode — when the code is unset or
 * matches no `countries` row, mirroring `useDefaultCountryId()` on the client:
 * an unresolvable code degrades to the pre-existing worldwide behaviour instead
 * of emptying every select.
 */
final class NationalScope
{
    /**
     * Id of the configured default country, or null when national mode is off
     * (code unset) or the code resolves to no row.
     */
    public static function countryId(): ?int
    {
        $iso2 = strtoupper(trim((string) config('geo.default_country_iso2', '')));

        if ($iso2 === '') {
            return null;
        }

        $countryId = Country::query()->where('iso2', $iso2)->value('id');

        return $countryId !== null ? (int) $countryId : null;
    }
}
