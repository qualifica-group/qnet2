<?php

namespace App\Http\Controllers\PurchaseOrders\Concerns;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseOrders\PurchaseOrderService;

/**
 * The order detail (resource + permissions block) the receipt and residual
 * endpoints answer with, so the frontend refreshes the order from the write.
 */
trait RespondsWithPurchaseOrderDetail
{
    /**
     * @return array{0: PurchaseOrderResource, 1: array<string, mixed>} resource and its permissions block
     */
    private function orderDetail(User $actor, PurchaseOrder $order): array
    {
        $detail = app(PurchaseOrderService::class)->detail($order);
        $permissions = app(ResourcePermissionsBuilder::class)->build(app(AuthorizationRegistry::class)->resolve('purchase-orders'), $actor, $detail);

        return [new PurchaseOrderResource($detail, $permissions), $permissions];
    }
}
