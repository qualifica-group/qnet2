<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\PurchaseRequestLineStatuses\UpdatePurchaseRequestLineStatusData;
use App\Models\PurchaseRequestLineStatusLabel;
use App\Services\Lookups\LookupOrderManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Business logic of the `purchase-request-line-statuses` resource (spec 0248):
 * the presentation (name, color, icon, order) of the line status enum cases.
 * There is no create/delete: the rows are the enum cases.
 */
class PurchaseRequestLineStatusLabelService
{
    public function __construct(private readonly LookupOrderManager $orderManager) {}

    public function update(PurchaseRequestLineStatusLabel $status, UpdatePurchaseRequestLineStatusData $data): PurchaseRequestLineStatusLabel
    {
        $status->fill($data->submittedAttributes())->save();

        return $status->fresh();
    }

    /**
     * @param  array<int, int>  $orderedIds  exactly every row (422 otherwise)
     * @return Collection<int, PurchaseRequestLineStatusLabel>
     */
    public function reorder(array $orderedIds): Collection
    {
        /** @var Collection<int, PurchaseRequestLineStatusLabel> $reordered */
        $reordered = $this->orderManager->reorder(PurchaseRequestLineStatusLabel::class, $orderedIds);

        return $reordered;
    }

    /**
     * Every row in display order: the catalog the frontend and the grids read.
     *
     * @return Collection<int, PurchaseRequestLineStatusLabel>
     */
    public function catalog(): Collection
    {
        return PurchaseRequestLineStatusLabel::query()->orderBy('sort_order')->orderBy('id')->get();
    }
}
