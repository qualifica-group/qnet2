<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Bulk status change (spec 0433): every order goes through
 * PurchaseOrderStatusService in its own transaction; the ones that cannot move
 * are reported with the reason of the single change and left untouched.
 */
class PurchaseOrderBulkStatusService
{
    public function __construct(
        private readonly PurchaseOrderStatusService $statusService,
    ) {}

    /**
     * @param  list<int>  $ids  distinct order ids
     * @return array{applied: bool, to_status: string, total: int, changed: list<array{id: int, code: string}>, skipped: list<array{id: int, code: string, reason: string}>}
     */
    public function handle(User $actor, array $ids, PurchaseOrderStatus $to, bool $dry_run): array
    {
        // Step 1: load the orders in one query, keeping the requested order
        $orders = PurchaseOrder::query()->whereKey($ids)->get()->keyBy('id');

        $changed = [];
        $skipped = [];

        foreach ($ids as $id) {
            /** @var PurchaseOrder $order */
            $order = $orders[$id];

            // Step 2: decide (and, unless dry run, apply) the order
            $reason = $this->process($order, $to, $actor, $dry_run);

            if ($reason === null) {
                $changed[] = ['id' => $order->id, 'code' => $order->code()];
            } else {
                $skipped[] = ['id' => $order->id, 'code' => $order->code(), 'reason' => $reason];
            }
        }

        // Step 3: summary
        return [
            'applied' => ! $dry_run,
            'to_status' => $to->value,
            'total' => count($ids),
            'changed' => $changed,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return string|null the reason the order is skipped, null when it moves
     */
    private function process(PurchaseOrder $order, PurchaseOrderStatus $to, User $actor, bool $dry_run): ?string
    {
        if ($order->status === $to) {
            return __('purchase_orders.bulk_already_in_status');
        }

        try {
            $dry_run ? $this->statusService->check($order, $to, $actor) : $this->statusService->change($order, $to, $actor);
        } catch (AuthorizationException $exception) {
            return $exception->getMessage();
        } catch (ValidationException $exception) {
            return (string) $exception->validator->errors()->first();
        }

        return null;
    }
}
