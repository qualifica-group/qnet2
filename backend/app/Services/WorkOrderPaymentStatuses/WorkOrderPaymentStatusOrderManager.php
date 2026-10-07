<?php

namespace App\Services\WorkOrderPaymentStatuses;

use App\Models\WorkOrderPaymentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * `sort_order` placement/resequencing for the `work-order-payment-statuses`
 * domain (spec 0201): every row is a plain user-owned row (no system rows), so
 * the simple resequencer of the payment methods applies.
 */
class WorkOrderPaymentStatusOrderManager
{
    private const int STEP = 10;

    /**
     * The `sort_order` a brand-new row should be created with: the last
     * row's order + STEP, or STEP when the table is empty.
     */
    public function placeNew(): int
    {
        $lastOrder = WorkOrderPaymentStatus::query()->max('sort_order');

        return $lastOrder !== null ? $lastOrder + self::STEP : self::STEP;
    }

    /**
     * Resequences every row to $orderedIds' order (10, 20, 30, ...) and
     * returns the fresh, complete, ordered list. $orderedIds must be EXACTLY
     * the full set of ids (no duplicate, none missing, none inexistent) —
     * validated here so the guard holds regardless of caller (defense in
     * depth beyond the FormRequest's own `distinct` rule).
     *
     * @param  array<int, int>  $orderedIds
     * @return Collection<int, WorkOrderPaymentStatus>
     *
     * @throws HttpException 422
     */
    public function reorder(array $orderedIds): Collection
    {
        return DB::transaction(function () use ($orderedIds): Collection {
            $this->assertValidReorderSet($orderedIds);

            $sortOrder = self::STEP;

            foreach ($orderedIds as $id) {
                WorkOrderPaymentStatus::query()->where('id', $id)->update(['sort_order' => $sortOrder]);
                $sortOrder += self::STEP;
            }

            return WorkOrderPaymentStatus::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
        });
    }

    /**
     * $orderedIds must be exactly the full id set: no duplicates, none
     * missing, none inexistent.
     *
     * @param  array<int, int>  $orderedIds
     *
     * @throws HttpException 422
     */
    private function assertValidReorderSet(array $orderedIds): void
    {
        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            abort(422, 'ordered_ids contains duplicate ids.');
        }

        $existingIds = WorkOrderPaymentStatus::query()->pluck('id')->all();

        if (array_diff($orderedIds, $existingIds) !== [] || array_diff($existingIds, $orderedIds) !== []) {
            abort(422, 'ordered_ids must contain exactly every work order payment status id (none missing, none inexistent).');
        }
    }
}
