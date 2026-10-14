<?php

declare(strict_types=1);

namespace App\Tables\PurchaseOrders;

use App\Http\Requests\PurchaseOrders\PurchaseOrderWriteRequest;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseOrders\PurchaseOrderService;
use App\Services\Table\FormRequestCellValidator;

/**
 * The write behind the ODA grid's inline cell edit (spec 0257, D-3): the
 * cell's one field goes through PurchaseOrderWriteRequest and then
 * PurchaseOrderService::update() — the same pair PATCH
 * /api/purchase-orders/{purchaseOrder} runs, so the supplier flag, the
 * company/site check and the field permissions are the form's own. The lines
 * are never sent, so they never change from a cell.
 */
final class PurchaseOrderCellWriter
{
    private const string ROUTE_PARAMETER = 'purchaseOrder';

    public function __construct(
        private readonly FormRequestCellValidator $formRequest,
        private readonly PurchaseOrderService $service,
    ) {}

    public function write(PurchaseOrder $order, string $fieldKey, mixed $value, User $actor): PurchaseOrder
    {
        $request = $this->formRequest->validate(PurchaseOrderWriteRequest::class, self::ROUTE_PARAMETER, $order, $actor, [$fieldKey => $value]);

        return $this->service->update($order, $request->toData(), $actor);
    }
}
