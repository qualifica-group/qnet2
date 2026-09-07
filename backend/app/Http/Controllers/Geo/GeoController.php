<?php

namespace App\Http\Controllers\Geo;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Geo\ListCitiesRequest;
use App\Http\Requests\Geo\ListProvincesRequest;
use App\Http\Requests\Geo\ListStatesRequest;
use App\Http\Resources\CityResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\ProvinceResource;
use App\Http\Resources\StateResource;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Support\Geo\GeoNameLocalizer;
use App\Support\Geo\NationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Read-only geo reference endpoints powering the address country → state → city
 * cascade selects (ADR 0010).
 *
 * Deliberate architectural exception: the controller queries Eloquent directly
 * instead of going through a Service. There is NO business logic here — only a
 * bounded reference lookup (ordered list, required parent filter, optional name
 * search, hard result cap) — so a GeoService would be an empty pass-through. The
 * geo models are read-only reference data (Country / State / City): no Policy,
 * no Factory-by-default, no activity log, and no per-resource permission. The
 * only gate is auth:sanctum (plus a throttle on the route group).
 *
 * The required parent filter (country_id on states, state_id on provinces,
 * province_id/state_id on cities) is enforced by the FormRequest → a missing/
 * unknown parent is a 422, never an unbounded query. Each endpoint selects only
 * the columns its Resource exposes, avoiding N+1 and over-fetching.
 */
class GeoController extends BaseApiController
{
    /**
     * Maximum number of cities returned by a single search, to keep the
     * reference lookup bounded regardless of the filter.
     */
    private const int CITY_RESULT_LIMIT = 50;

    /**
     * GET /api/countries — every country, ordered by name, for the first select.
     */
    public function countries(): JsonResponse
    {
        try {
            $countries = Country::query()
                ->select(['id', 'name', 'iso2'])
                ->orderBy('name')
                ->get();

            return $this->ok(CountryResource::collection($countries));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/states?country_id={id} — states of a country, ordered by name.
     */
    public function states(ListStatesRequest $request): JsonResponse
    {
        try {
            $states = State::query()
                ->select(['id', 'name', 'country_id'])
                ->where('country_id', $request->countryId())
                ->orderBy('name')
                ->get();

            return $this->ok(StateResource::collection($states));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/provinces?state_id={id} — provinces of a state, ordered by name.
     */
    public function provinces(ListProvincesRequest $request): JsonResponse
    {
        try {
            $provinces = Province::query()
                ->select(['id', 'name', 'state_id'])
                ->where('state_id', $request->stateId())
                ->orderBy('name')
                ->get();

            return $this->ok(ProvinceResource::collection($provinces));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/cities?province_id={id}|state_id={id}&search={q}&offset={n} —
     * cities of a province (preferred) or of a state, or — for city-first
     * selection — an unscoped name lookup when neither parent is given (a
     * non-empty `search` is then mandatory, enforced by ListCitiesRequest).
     * Ordered by name, optionally filtered by a name LIKE, returned one page at
     * a time (CITY_RESULT_LIMIT rows); `offset` skips the already-loaded rows.
     *
     * The parentless city-first branch is the only one nothing scopes, so in
     * national mode it is bounded by the configured country (NationalScope) —
     * without it a two-letter search returns localities from the whole world.
     * The parent-scoped branches are left alone: their parent already bounds
     * them, so a foreign city stays reachable through the full cascade.
     */
    public function cities(ListCitiesRequest $request): JsonResponse
    {
        try {
            $search = $request->search();
            $provinceId = $request->provinceId();
            $stateId = $request->stateId();
            $nationalCountryId = ($provinceId === null && $stateId === null)
                ? NationalScope::countryId()
                : null;

            $cities = City::query()
                ->select(['id', 'name', 'country_id', 'state_id', 'province_id'])
                ->when($provinceId !== null, fn ($query) => $query->where('province_id', $provinceId))
                ->when($provinceId === null && $stateId !== null, fn ($query) => $query->where('state_id', $stateId))
                ->when($nationalCountryId !== null, fn ($query) => $query->where('country_id', $nationalCountryId))
                ->when($search !== null, fn ($query) => $this->applyCityNameSearch($query, (string) $search))
                ->orderBy('name')
                ->orderBy('id')
                ->offset($request->offset())
                ->limit(self::CITY_RESULT_LIMIT)
                ->get();

            return $this->ok(CityResource::collection($cities));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * Narrows the city lookup to $search, in the two spellings a row can carry.
     *
     * The `name` column stores the reference dataset's ENGLISH name while the
     * Resource serves the Italian display name, so a term typed in Italian must
     * also reach the rows it localizes ("napoli" -> the row stored as "Naples").
     *
     * Those aliased rows are also ranked FIRST: sorted on the English column,
     * "Rome" falls behind every "Romagn..." and lands past CITY_RESULT_LIMIT —
     * i.e. searching "roma" would still not surface Roma on the first page. The
     * ranking is part of the SQL (not a PHP merge) so `offset` paging stays
     * consistent across pages.
     *
     * @param  Builder<City>  $query
     * @return Builder<City>
     */
    private function applyCityNameSearch(Builder $query, string $search): Builder
    {
        $englishMatches = GeoNameLocalizer::englishNamesStartingWith($search);

        // Escape the LIKE metacharacters (\ % _) so a search term containing
        // them matches literally instead of acting as a wildcard; the trailing
        // % stays the intended prefix match.
        $query->where(function (Builder $nameQuery) use ($search, $englishMatches): void {
            $nameQuery->where('name', 'like', addcslashes($search, '\\%_').'%')
                ->when(
                    $englishMatches !== [],
                    fn (Builder $inner) => $inner->orWhereIn('name', $englishMatches)
                );
        });

        if ($englishMatches === []) {
            return $query;
        }

        // Placeholders are derived from the ARRAY SIZE and the values are bound,
        // so nothing from the request is interpolated into the SQL string; the
        // values themselves come from GeoNameLocalizer's closed constant map.
        $placeholders = implode(',', array_fill(0, count($englishMatches), '?'));

        return $query->orderByRaw("CASE WHEN name IN ({$placeholders}) THEN 0 ELSE 1 END", $englishMatches);
    }
}
