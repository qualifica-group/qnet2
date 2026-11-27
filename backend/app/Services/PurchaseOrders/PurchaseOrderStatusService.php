<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Status changes of a purchase order (spec 0210, D-9): the matrix lives in the
 * PurchaseOrderStatus enum, the permission depends on the target (approved
 * needs `approve`, every other state `changeStatus`). Cancelling an order
 * releases its request lines (spec 0244, D-3); fulfilment is derived from the
 * receipts (spec 0254), so it has no manual transition here.
 */
class PurchaseOrderStatusService
{
    public function __construct(
        private readonly PurchaseRequestLineLinker $linker,
    ) {}

    public function change(PurchaseOrder $order, PurchaseOrderStatus $to, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $to, $actor): PurchaseOrder {
            // Step 1: serialize on the order
            $locked = PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            // Step 2: permission (403), matrix (422) and deliveries guard (422)
            $this->check($locked, $to, $actor);

            // Step 3: apply
            $locked->forceFill(['status' => $to])->save();

            // Step 4: propagate to the linked request lines (same transaction)
            $this->propagate($locked, $to, $actor);

            return $locked;
        });
    }

    /**
     * Read-only gate of a change, shared with the bulk dry run so both decide
     * the same way: permission of the target (403), matrix (422 on to_status),
     * deliveries guard (422).
     */
    public function check(PurchaseOrder $order, PurchaseOrderStatus $to, User $actor): void
    {
        Gate::forUser($actor)->authorize($this->ability($to), $order);

        if (! $order->status->canTransitionTo($to)) {
            throw ValidationException::withMessages(['to_status' => [__('This status change is not allowed for the order.')]]);
        }

        // An order with deliveries cannot go back or be cancelled (D-5)
        $this->assertNoReceiptsToDiscard($order, $to);
    }

    /**
     * The transitions the actor may perform: the matrix filtered by permission.
     *
     * @return array<int, PurchaseOrderStatus>
     */
    public function transitionsFor(PurchaseOrder $order, User $actor): array
    {
        return array_values(array_filter(
            $order->status->allowedTransitions(),
            fn (PurchaseOrderStatus $target): bool => $actor->can($this->ability($target), $order),
        ));
    }

    /**
     * Lock order: the order (held by the caller), its lines, then the request
     * lines and requests (inside the linker).
     */
    private function propagate(PurchaseOrder $order, PurchaseOrderStatus $to, User $actor): void
    {
        if ($to !== PurchaseOrderStatus::Cancelled) {
            return;
        }

        $lines = $order->lines()->orderBy('id')->lockForUpdate()->get();

        $this->linker->unlink($lines, $actor, __('purchase_request_line_links.order_cancelled'));
        $lines->each(fn (PurchaseOrderLine $line): bool => $line->forceFill(['purchase_request_line_id' => null])->save());
    }

    private function assertNoReceiptsToDiscard(PurchaseOrder $order, PurchaseOrderStatus $to): void
    {
        $discarding = in_array($to, [PurchaseOrderStatus::InValidation, PurchaseOrderStatus::Draft, PurchaseOrderStatus::Cancelled], true);

        if ($discarding && ($order->receipts()->exists() || $order->lines()->whereNotNull('residual_closed_at')->exists())) {
            throw ValidationException::withMessages(['to_status' => [__('purchase_orders.has_receipts')]]);
        }
    }

    private function ability(PurchaseOrderStatus $to): string
    {
        return $to === PurchaseOrderStatus::Approved ? 'approve' : 'changeStatus';
    }
}
