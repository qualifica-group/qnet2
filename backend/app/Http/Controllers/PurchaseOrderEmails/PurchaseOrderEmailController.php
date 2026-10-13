<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseOrderEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailController;
use App\Http\Requests\PurchaseOrderEmails\StorePurchaseOrderEmailRequest;
use App\Http\Requests\WorkOrderEmails\UpdateOutboundEmailRequest;
use App\Http\Resources\OutboundEmailResource;
use App\Models\PurchaseOrder;
use App\Services\OutboundEmails\EmailOwnerRegistry;
use App\Services\OutboundEmails\OutboundEmailService;
use App\Services\PurchaseOrders\PurchaseOrderEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET/POST/PATCH/DELETE `/api/purchase-orders/{purchaseOrder}/emails[/{email}]`
 * and `.../send` (spec 0210): the purchase order binding of
 * OwnerEmailController. Authorization: PurchaseOrderPolicy view/send.
 */
class PurchaseOrderEmailController extends OwnerEmailController
{
    public function __construct(
        EmailOwnerRegistry $owners,
        OutboundEmailService $service,
        private readonly PurchaseOrderEmailService $purchaseOrderEmails,
    ) {
        parent::__construct($owners, $service);
    }

    public function index(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->listEmails($request, $purchaseOrder);
    }

    /**
     * Creates the draft with the generated PDF attached unless `attach_pdf` is false.
     */
    public function store(StorePurchaseOrderEmailRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->authorizeSend($purchaseOrder);

            $email = $this->purchaseOrderEmails->createDocumentDraft($purchaseOrder, $this->actor($request), $request->toData(), $request->attachPdf());

            return $this->created(new OutboundEmailResource($email));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'store', $purchaseOrder);
        }
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder, int $email): JsonResponse
    {
        return $this->showEmail($request, $purchaseOrder, $email);
    }

    public function update(UpdateOutboundEmailRequest $request, PurchaseOrder $purchaseOrder, int $email): JsonResponse
    {
        return $this->updateEmail($request, $purchaseOrder, $email);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, int $email): JsonResponse
    {
        return $this->destroyEmail($request, $purchaseOrder, $email);
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder, int $email): JsonResponse
    {
        return $this->sendEmail($request, $purchaseOrder, $email);
    }
}
