<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestLineStatusLog;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestLineLocker;

/**
 * The link between purchase order lines and purchase request lines (spec 0210,
 * D-17/D-18). The writes here are an effect of the permission on the ORDER, so
 * they skip the capability checks of PurchaseRequestLineStatusService and never
 * close or reopen a request. Must run inside the caller's transaction (the
 * request lines are locked here).
 */
final class PurchaseRequestLineLinker
{
    public function __construct(private readonly PurchaseRequestLineLocker $locker) {}

    /**
     * Release the request lines linked to the given order lines (about to be
     * deleted): each goes back to approved with a log row and loses its ODA
     * reference. A closed request stays closed.
     *
     * @param  iterable<PurchaseOrderLine>  $orderLines
     */
    public function unlink(iterable $orderLines, User $actor): void
    {
        $ids = collect($orderLines)
            ->pluck('purchase_request_line_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        foreach ($this->locker->lines($ids) as $line) {
            $this->release($line, $actor);
        }
    }

    private function release(PurchaseRequestLine $line, User $actor): void
    {
        $from = $line->status;
        $line->oda_reference = null;
        $line->status = PurchaseRequestLineStatus::Approved;
        $line->save();

        if ($from === PurchaseRequestLineStatus::Approved) {
            return;
        }

        PurchaseRequestLineStatusLog::query()->create([
            'purchase_request_line_id' => $line->id,
            'user_id' => $actor->id,
            'from_status' => $from,
            'to_status' => PurchaseRequestLineStatus::Approved,
            'reason' => __('Purchase order deleted'),
            'bulk_group_id' => null,
        ]);
    }
}
