<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseOrderEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailAttachmentController;
use App\Http\Requests\PurchaseOrderEmails\ImportPurchaseOrderEmailAttachmentRequest;
use App\Http\Requests\WorkOrderEmails\StoreWorkOrderEmailAttachmentRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `/api/purchase-orders/{purchaseOrder}/emails/{email}/attachments/*` (spec
 * 0210): the purchase order binding of OwnerEmailAttachmentController.
 */
class PurchaseOrderEmailAttachmentController extends OwnerEmailAttachmentController
{
    public function store(StoreWorkOrderEmailAttachmentRequest $request, PurchaseOrder $purchaseOrder, int $email): JsonResponse
    {
        return $this->storeAttachment($request, $purchaseOrder, $email);
    }

    public function import(ImportPurchaseOrderEmailAttachmentRequest $request, PurchaseOrder $purchaseOrder, int $email): JsonResponse
    {
        return $this->importAttachments($request, $purchaseOrder, $email);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, int $email, int $attachment): JsonResponse
    {
        return $this->destroyAttachment($request, $purchaseOrder, $email, $attachment);
    }

    public function download(Request $request, PurchaseOrder $purchaseOrder, int $email, int $attachment): StreamedResponse|JsonResponse
    {
        return $this->downloadAttachment($request, $purchaseOrder, $email, $attachment);
    }
}
