<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestLineStatusLog;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestLineLocker;
use Illuminate\Database\Eloquent\Collection;

/**
 * The link between purchase order lines and purchase request lines (spec 0210,
 * D-17; spec 0244, D-2/D-3). The writes here are an effect of the permission on
 * the ORDER, so they skip the capability checks of PurchaseRequestLineStatusService
 * and never close or reopen a request. Must run inside the caller's transaction
 * (the request lines are locked here).
 */
final class PurchaseRequestLineLinker
{
    public function __construct(private readonly PurchaseRequestLineLocker $locker) {}

    /**
     * Point the request lines at the code of the order that now holds them
     * (generation from RDA, D-17; merge, D-19). Status is not touched.
     *
     * @param  array<int, string>  $codes  request line id => order code
     */
    public function link(array $codes): void
    {
        if ($codes === []) {
            return;
        }

        foreach ($this->locker->lines(array_keys($codes)) as $line) {
            $line->oda_reference = $codes[$line->id];
            $line->save();
        }
    }

    /**
     * Release the request lines linked to the given order lines (D-2): an
     * `ordered` line goes back to approved, or to on_hold when its request is
     * closed (a closed request is never reopened); any other state is kept. The
     * line always loses its ODA reference.
     *
     * @param  iterable<PurchaseOrderLine>  $orderLines
     * @param  string|null  $reason  log reason, defaults to "order deleted"
     */
    public function unlink(iterable $orderLines, User $actor, ?string $reason = null): void
    {
        $reason ??= __('purchase_request_line_links.order_deleted');

        foreach ($this->lockLinked($orderLines) as $line) {
            $to = $line->status === PurchaseRequestLineStatus::Ordered
                ? ($line->purchaseRequest->isClosed() ? PurchaseRequestLineStatus::OnHold : PurchaseRequestLineStatus::Approved)
                : $line->status;

            $this->transition($line, $to, $actor, $reason, clearReference: true);
        }
    }

    /**
     * The order was fulfilled (D-3): its `ordered` request lines become received.
     * Returns the locked requests involved, so the caller can close the complete ones.
     *
     * @param  iterable<PurchaseOrderLine>  $orderLines
     * @return Collection<int, PurchaseRequest>
     */
    public function receive(iterable $orderLines, User $actor): Collection
    {
        $reason = __('purchase_request_line_links.order_fulfilled');
        $lines = $this->lockLinked($orderLines);

        foreach ($lines as $line) {
            if ($line->status === PurchaseRequestLineStatus::Ordered) {
                $this->transition($line, PurchaseRequestLineStatus::Received, $actor, $reason);
            }
        }

        return $this->locker->requests($lines);
    }

    /**
     * Lock the request lines linked to the order lines, with their request loaded
     * (no lazy loading).
     *
     * @param  iterable<PurchaseOrderLine>  $orderLines
     * @return Collection<int, PurchaseRequestLine>
     */
    private function lockLinked(iterable $orderLines): Collection
    {
        $ids = collect($orderLines)
            ->pluck('purchase_request_line_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return new Collection;
        }

        return $this->locker->lines($ids)->load('purchaseRequest:id,status');
    }

    /**
     * The only writer of a request line status for an order effect: persist and
     * log the change (no log when the status stays the same).
     */
    private function transition(PurchaseRequestLine $line, PurchaseRequestLineStatus $to, User $actor, string $reason, bool $clearReference = false): void
    {
        $from = $line->status;
        $line->status = $to;

        if ($clearReference) {
            $line->oda_reference = null;
        }

        $line->save();

        if ($from === $to) {
            return;
        }

        PurchaseRequestLineStatusLog::query()->create([
            'purchase_request_line_id' => $line->id,
            'user_id' => $actor->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'bulk_group_id' => null,
        ]);
    }
}
