<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseOrderEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailTemplateRenderController;
use App\Http\Requests\WorkOrderEmails\RenderWorkOrderEmailTemplateRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/purchase-orders/{purchaseOrder}/emails/render-template (spec
 * 0210): the purchase order binding of OwnerEmailTemplateRenderController.
 */
class PurchaseOrderEmailTemplateRenderController extends OwnerEmailTemplateRenderController
{
    public function __invoke(RenderWorkOrderEmailTemplateRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->renderTemplate($request, $purchaseOrder);
    }
}
