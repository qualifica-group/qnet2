<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\UnitsOfMeasure\CreateUnitOfMeasureData;
use App\DataObjects\UnitsOfMeasure\UpdateUnitOfMeasureData;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Collection;

/**
 * Business logic for the `units-of-measure` resource (spec 0088): a lean,
 * full-CRUD lookup (code/name/symbol/description) qualifying a Product's
 * quantity. The controller stays thin; this Service is the single
 * authority, mirroring VatRateService for leanness.
 */
class UnitOfMeasureService
{
    public function create(CreateUnitOfMeasureData $data): UnitOfMeasure
    {
        return UnitOfMeasure::create($data->attributes());
    }

    public function update(UnitOfMeasure $unitOfMeasure, UpdateUnitOfMeasureData $data): UnitOfMeasure
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $unitOfMeasure->fill($attributes)->save();

        return $unitOfMeasure->fresh();
    }

    /**
     * Restrictive delete (spec 0088, D-7): a unit still referenced by a
     * Product OR a Quote line cannot be removed. Both relations are checked
     * (unlike VatRateService::delete(), a preexisting, out-of-scope gap noted
     * in D-7) — mirrors SourceService::delete()'s double guard.
     */
    public function delete(UnitOfMeasure $unitOfMeasure): void
    {
        if ($unitOfMeasure->products()->exists()) {
            abort(409, 'This unit of measure is used by a product and cannot be deleted.');
        }

        if ($unitOfMeasure->quoteLines()->exists()) {
            abort(409, 'This unit of measure is used by a quote line and cannot be deleted.');
        }

        $unitOfMeasure->delete();
    }

    /**
     * Minimal, searchable, paginated unit of measure list for the for-select
     * standard (ADR 0011), mirroring VatRateService::forSelect.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = UnitOfMeasure::query()->select(['id', 'name', 'symbol']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, UnitOfMeasure> $page */
        $page = $base->orderBy('name')
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
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, UnitOfMeasure>  $page
     * @return Collection<int, UnitOfMeasure>
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

        /** @var Collection<int, UnitOfMeasure> $hydrated */
        $hydrated = UnitOfMeasure::query()
            ->select(['id', 'name', 'symbol'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
