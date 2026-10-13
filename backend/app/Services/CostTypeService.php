<?php

namespace App\Services;

use App\DataObjects\CostTypes\CreateCostTypeData;
use App\DataObjects\CostTypes\UpdateCostTypeData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\CostType;
use App\Services\Lookups\LookupOrderManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic for the `cost-types` resource (spec 0210): the lookup
 * classifying purchase order lines. `sort_order` is server-managed (spec 0249).
 */
class CostTypeService
{
    public function __construct(private readonly LookupOrderManager $orderManager) {}

    public function create(CreateCostTypeData $data): CostType
    {
        return CostType::create([
            ...$data->attributes(),
            'sort_order' => $this->orderManager->placeNew(CostType::class),
        ]);
    }

    /**
     * Resequences every cost type to $orderedIds' order.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, CostType>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, CostType> $reordered */
        $reordered = $this->orderManager->reorder(CostType::class, $orderedIds);

        return $reordered;
    }

    public function update(CostType $costType, UpdateCostTypeData $data): CostType
    {
        $costType->fill($data->submittedAttributes())->save();

        return $costType->fresh();
    }

    /**
     * Restrictive delete: a cost type still used by a purchase order line or a
     * purchase invoice cannot be removed (it can be deactivated instead).
     */
    public function delete(CostType $costType): void
    {
        if ($costType->purchaseOrderLines()->exists() || $costType->purchaseInvoices()->exists()) {
            abort(409, __('cost_types.in_use'));
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
        return CostType::query()->select(['id', 'name', 'nature', 'color', 'is_active', 'sort_order'])
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
