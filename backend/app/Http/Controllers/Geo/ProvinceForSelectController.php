<?php

namespace App\Http\Controllers\Geo;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Geo\ProvinceForSelectRequest;
use App\Http\Resources\ProvinceForSelectResource;
use App\Models\Province;
use App\Support\Geo\NationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Throwable;

/**
 * GET /api/provinces/for-select — searchable, paginated province list feeding
 * a RELATION field that targets provinces (spec 0225, ADR 0011), mirroring
 * StateForSelectController.
 *
 * Same documented exception as GeoController: provinces are read-only
 * reference data with no Policy and no per-resource permission, so this
 * controller queries Eloquent directly; the only gate is auth:sanctum. The
 * request carries no parent filter, so the list is bounded by the configured
 * national country (NationalScope); `ids[]` hydration bypasses that filter on
 * purpose.
 */
class ProvinceForSelectController extends BaseApiController
{
    public function __invoke(ProvinceForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->forSelect($request->toData());

            return $this->paginatedResponse(
                ProvinceForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function forSelect(ForSelectQuery $query): ForSelectResult
    {
        // Step 1: bound the list to the national country, then apply the search
        $base = $this->baseQuery();
        $nationalCountryId = NationalScope::countryId();

        if ($nationalCountryId !== null) {
            $base->where('country_id', $nationalCountryId);
        }

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        // Step 2: count and page
        $total = (clone $base)->count();

        /** @var Collection<int, Province> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        // Step 3: append the ids[] the edit form needs to hydrate
        return new ForSelectResult(
            items: $this->appendHydratedIds($page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @return Builder<Province>
     */
    private function baseQuery(): Builder
    {
        return Province::query()
            ->select(['id', 'name', 'state_id'])
            ->with('state:id,name');
    }

    /**
     * Append the requested `ids[]` not already on the page, bypassing search
     * and the national filter: a saved value must always render. Total is
     * unaffected.
     *
     * @param  Collection<int, Province>  $page
     * @return Collection<int, Province>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Province> $hydrated */
        $hydrated = $this->baseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
