<?php

namespace App\Services;

use App\DataObjects\CostTypes\CreateCostTypeData;
use App\DataObjects\CostTypes\UpdateCostTypeData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\CostType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Business logic for the `cost-types` resource (spec 0210): the lookup
 * classifying purchase order lines.
 */
class CostTypeService
{
    public function create(CreateCostTypeData $data): CostType
    {
        return CostType::create($data->attributes());
    }

    public function update(CostType $costType, UpdateCostTypeData $data): CostType
    {
        $costType->fill($data->submittedAttributes())->save();

        return $costType->fresh();
    }

    /**
     * Restrictive delete: a cost type still used by a purchase order line cannot be removed.
     */
    public function delete(CostType $costType): void
    {
        if ($costType->purchaseOrderLines()->exists()) {
            abort(409, 'This cost type is used by a purchase order line and cannot be deleted.');
        }

        $costType->delete();
    }

    /**
     * Active cost types only, by display order. The requested `ids[]`
     * (edit-mode hydration) are appended even when inactive, so an existing
     * line keeps showing its type.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->orderedQuery()->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, CostType> $page */
        $page = $base->offset($query->offset)->limit($query->limit)->get();

        return new ForSelectResult(
            items: $this->appendHydratedIds($page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @return Builder<CostType>
     */
    private function orderedQuery(): Builder
    {
        return CostType::query()->select(['id', 'name', 'is_active', 'sort_order'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, CostType>  $page
     * @return Collection<int, CostType>
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

        return $page->concat($this->orderedQuery()->whereIn('id', $missingIds)->get());
    }
}
