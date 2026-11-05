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
    /** Relation path the placement (account > group) of a for-select item reads. */
    public const string PLACEMENT_RELATION = 'chartAccount.parent';

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
     * Restrictive delete: a cost type still used by a purchase order line, a
     * purchase invoice, a product or a commessa cost (spec 0251) cannot be
     * removed (it can be deactivated instead).
     */
    public function delete(CostType $costType): void
    {
        if ($costType->purchaseOrderLines()->exists()
            || $costType->purchaseInvoices()->exists()
            || $costType->products()->exists()
            || $costType->workOrderCosts()->exists()) {
            abort(409, __('cost_types.in_use'));
        }

        $costType->delete();
    }

    /**
     * Active cost types only, placed ones first by natural code (`61.2` before
     * `61.10`), then the unplaced ones by name (spec 0350, D-12). Searched on
     * code and name. The requested `ids[]` (edit-mode hydration) are appended
     * even when inactive and do not inflate the total. The lookup is small, so
     * ordering and paging happen in memory (no orderByRaw).
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        // Step 1: the selectable population, in display order
        $selectable = $this->selectable($query)->get()->sort($this->byDisplayOrder(...))->values();

        // Step 2: the requested window, then the hydrated ids not already in it
        $page = $selectable->slice($query->offset, $query->limit)->values();

        return new ForSelectResult(
            items: $this->appendHydratedIds($page, $query),
            total: $selectable->count(),
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @return Builder<CostType>
     */
    private function selectable(ForSelectQuery $query): Builder
    {
        return CostType::query()->with(self::PLACEMENT_RELATION)
            ->where('is_active', true)
            ->when($query->hasSearch(), static fn (Builder $costTypes) => $costTypes->where(
                static fn (Builder $match) => $match->where('code', 'like', "%{$query->search}%")->orWhere('name', 'like', "%{$query->search}%"),
            ));
    }

    private function byDisplayOrder(CostType $a, CostType $b): int
    {
        $aPlaced = $a->chart_account_id !== null;

        if ($aPlaced !== ($b->chart_account_id !== null)) {
            return $aPlaced ? -1 : 1;
        }

        $byLabel = $aPlaced
            ? strnatcasecmp((string) $a->code, (string) $b->code)
            : strnatcasecmp($a->name, $b->name);

        return $byLabel !== 0 ? $byLabel : $a->id <=> $b->id;
    }

    /**
     * @param  Collection<int, CostType>  $page
     * @return Collection<int, CostType>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        return $page->concat(CostType::query()->with(self::PLACEMENT_RELATION)->whereIn('id', $missingIds)->get()->sort($this->byDisplayOrder(...)))->values();
    }
}
