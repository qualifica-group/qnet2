<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Closing and reopening the residual of an order line (spec 0254, D-3, D-8):
 * "the missing goods will not arrive". A closed line counts as complete and its
 * billable ceiling is the received quantity.
 */
final class PurchaseOrderLineResidualService
{
    public function __construct(private readonly PurchaseOrderFulfillmentSynchronizer $synchronizer) {}

    /** 409 when the order is not receivable, the line is complete, or billed beyond what was received. */
    public function close(PurchaseOrder $order, PurchaseOrderLine $line, string $reason, User $actor): void
    {
        DB::transaction(function () use ($order, $line, $reason, $actor): void {
            // Step 1: serialize on the order; only a receivable one accepts the closure
            $locked = $this->lock($order);

            if (! $locked->status->isReceivable()) {
                abort(409, __('purchase_orders.not_receivable'));
            }

            // Step 2: lock the lines and re-check the line on fresh figures
            $before = $this->synchronizer->lockedBalances($locked);
            $balance = $before->get($line->getKey()) ?? abort(404);

            if ($balance->isComplete()) {
                abort(409, __('purchase_orders.line_already_complete'));
            }

            if (bccomp($balance->invoicedTotal, $balance->received, 3) > 0) {
                abort(409, __('purchase_orders.residual_below_billed'));
            }

            // Step 3: apply
            PurchaseOrderLine::query()->findOrFail($line->getKey())
                ->forceFill(['residual_closed_at' => now(), 'residual_closed_by_id' => $actor->id, 'residual_close_reason' => $reason])
                ->save();

            // Step 4: status and request lines follow the lines
            $this->synchronizer->sync($locked, $before, $actor);
        });
    }

    /** 409 when the line has no closed residual or the order is cancelled. */
    public function reopen(PurchaseOrder $order, PurchaseOrderLine $line, User $actor): void
    {
        DB::transaction(function () use ($order, $line, $actor): void {
            // Step 1: serialize on the order
            $locked = $this->lock($order);

            if ($locked->status === PurchaseOrderStatus::Cancelled) {
                abort(409, __('purchase_orders.cancelled'));
            }

            // Step 2: lock the lines and re-check the line on fresh figures
            $before = $this->synchronizer->lockedBalances($locked);
            $balance = $before->get($line->getKey()) ?? abort(404);

            if (! $balance->residualClosed) {
                abort(409, __('purchase_orders.residual_not_closed'));
            }

            // Step 3: apply
            PurchaseOrderLine::query()->findOrFail($line->getKey())
                ->forceFill(['residual_closed_at' => null, 'residual_closed_by_id' => null, 'residual_close_reason' => null])
                ->save();

            // Step 4: status and request lines follow the lines
            $this->synchronizer->sync($locked, $before, $actor);
        });
    }

    private function lock(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
    }
}
