<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestClosureService;
use Illuminate\Support\Collection;

/**
 * Derives the status of an order from its lines and carries the line
 * completion over to the linked request lines (spec 0254, D-3, D-7). Must run
 * inside the caller's transaction, after the order lock.
 *
 * Lock order: order (caller), order lines (snapshot), request lines, requests.
 */
final class PurchaseOrderFulfillmentSynchronizer
{
    public function __construct(
        private readonly PurchaseOrderLineBillingLedger $ledger,
        private readonly PurchaseRequestLineLinker $linker,
        private readonly PurchaseRequestClosureService $closure,
    ) {}

    /**
     * Lock every line of the order (id order) and the document lines that
     * reference them, and return their balances: the figures the caller must
     * validate against, and the "before" state for sync().
     *
     * @return Collection<int, PurchaseOrderLineBalance> keyed by order line id
     */
    public function lockedBalances(PurchaseOrder $order): Collection
    {
        $lineIds = PurchaseOrderLine::query()->where('purchase_order_id', $order->getKey())->pluck('id')->all();

        return $this->ledger->forLines($lineIds, lock: true);
    }

    /**
     * @param  Collection<int, PurchaseOrderLineBalance>  $before  from lockedBalances(), taken before the change
     */
    public function sync(PurchaseOrder $order, Collection $before, User $actor): void
    {
        // Step 1: balances after the change
        $after = $this->ledger->forLines($before->keys()->all());

        // Step 2: header status from the lines
        $this->applyStatus($order, $after);

        // Step 3: request lines of the lines that changed completion
        $this->propagate($before, $after, $actor);
    }

    /**
     * @param  Collection<int, PurchaseOrderLineBalance>  $balances
     */
    private function applyStatus(PurchaseOrder $order, Collection $balances): void
    {
        $status = $this->derivedStatus($order->status, $balances);

        if ($status !== $order->status) {
            $order->forceFill(['status' => $status])->save();
        }
    }

    /**
     * @param  Collection<int, PurchaseOrderLineBalance>  $balances
     */
    private function derivedStatus(PurchaseOrderStatus $current, Collection $balances): PurchaseOrderStatus
    {
        if ($balances->isNotEmpty() && $balances->every(fn (PurchaseOrderLineBalance $balance): bool => $balance->isComplete())) {
            return PurchaseOrderStatus::Fulfilled;
        }

        $touched = $balances->contains(
            fn (PurchaseOrderLineBalance $balance): bool => $balance->residualClosed || bccomp($balance->received, '0', 3) > 0,
        );

        if ($touched) {
            return PurchaseOrderStatus::PartiallyFulfilled;
        }

        $derived = [PurchaseOrderStatus::PartiallyFulfilled, PurchaseOrderStatus::Fulfilled];

        return in_array($current, $derived, true) ? PurchaseOrderStatus::SentToSupplier : $current;
    }

    /**
     * @param  Collection<int, PurchaseOrderLineBalance>  $before
     * @param  Collection<int, PurchaseOrderLineBalance>  $after
     */
    private function propagate(Collection $before, Collection $after, User $actor): void
    {
        $completed = $after->filter(fn (PurchaseOrderLineBalance $balance, int $id): bool => $balance->isComplete() && ! $before[$id]->isComplete());
        $reopened = $after->filter(fn (PurchaseOrderLineBalance $balance, int $id): bool => ! $balance->isComplete() && $before[$id]->isComplete());

        if ($completed->isNotEmpty()) {
            foreach ($this->linker->receive($this->linkedLines($completed), $actor) as $request) {
                $this->closure->closeIfComplete($request, $actor);
            }
        }

        if ($reopened->isNotEmpty()) {
            $this->linker->revertReceive($this->linkedLines($reopened), $actor);
        }
    }

    /**
     * @param  Collection<int, PurchaseOrderLineBalance>  $balances
     * @return Collection<int, PurchaseOrderLine>
     */
    private function linkedLines(Collection $balances): Collection
    {
        return PurchaseOrderLine::query()
            ->whereIn('id', $balances->keys())
            ->whereNotNull('purchase_request_line_id')
            ->orderBy('id')
            ->get(['id', 'purchase_request_line_id']);
    }
}
