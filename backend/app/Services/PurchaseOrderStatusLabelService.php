<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseOrderStatusLabel;
use App\Services\Lookups\LookupOrderManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Business logic for the `purchase-order-statuses` presentation catalogue
 * (spec 0248): rename/recolour/re-icon a row, resequence all rows, list them
 * all. No create/delete: the rows are the PurchaseOrderStatus enum cases.
 */
class PurchaseOrderStatusLabelService
{
    public function __construct(private readonly LookupOrderManager $orderManager) {}

    /**
     * @param  array<string, mixed>  $attributes  validated name/color/icon only
     */
    public function update(PurchaseOrderStatusLabel $label, array $attributes): PurchaseOrderStatusLabel
    {
        // Unconditional save: a clean model runs no UPDATE query.
        $label->fill($attributes)->save();

        return $label->fresh();
    }

    /**
     * @param  array<int, int>  $orderedIds  exactly every row (422 otherwise)
     * @return Collection<int, PurchaseOrderStatusLabel>
     */
    public function reorder(array $orderedIds): Collection
    {
        /** @var Collection<int, PurchaseOrderStatusLabel> $reordered */
        $reordered = $this->orderManager->reorder(PurchaseOrderStatusLabel::class, $orderedIds);

        return $reordered;
    }

    /**
     * Every row in display order: the catalogue is tiny and fixed (one row per
     * enum case), so there is no search/pagination.
     *
     * @return Collection<int, PurchaseOrderStatusLabel>
     */
    public function all(): Collection
    {
        return PurchaseOrderStatusLabel::query()->orderBy('sort_order')->orderBy('id')->get();
    }
}
