<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Status changes of a purchase order (spec 0210, D-9): the matrix lives in the
 * PurchaseOrderStatus enum, the permission depends on the target (approved
 * needs `approve`, every other state `changeStatus`).
 */
class PurchaseOrderStatusService
{
    public function change(PurchaseOrder $order, PurchaseOrderStatus $to, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $to, $actor): PurchaseOrder {
            // Step 1: serialize on the order, then check the permission of the target (403)
            $locked = PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $actor->can($this->ability($to), $locked)) {
                throw new AuthorizationException;
            }

            // Step 2: the matrix decides (422 on to_status)
            if (! $locked->status->canTransitionTo($to)) {
                throw ValidationException::withMessages(['to_status' => [__('This status change is not allowed for the order.')]]);
            }

            // Step 3: apply
            $locked->forceFill(['status' => $to])->save();

            return $locked;
        });
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

    private function ability(PurchaseOrderStatus $to): string
    {
        return $to === PurchaseOrderStatus::Approved ? 'approve' : 'changeStatus';
    }
}
