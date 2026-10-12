<?php

namespace App\Http\Controllers\PurchaseInvoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseInvoices\UpdatePurchaseInvoiceInstallmentRequest;
use App\Http\Resources\PurchaseInvoiceInstallmentResource;
use App\Models\PurchaseInvoiceInstallment;
use App\Services\PurchaseInvoices\PurchaseInvoiceInstallmentUpdater;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Show and edit one payable installment (spec 0211). The list is the generic
 * `purchase-invoice-installments` table domain; the payment stays on
 * PurchaseInvoicePaymentController.
 *
 * @see PurchaseInvoiceInstallmentUpdater
 */
class PurchaseInvoiceInstallmentController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly PurchaseInvoiceInstallmentUpdater $updater) {}

    /**
     * GET /api/purchase-invoice-installments/{installment}
     */
    public function show(PurchaseInvoiceInstallment $installment): JsonResponse
    {
        try {
            $this->authorize('view', $installment);

            return $this->ok(new PurchaseInvoiceInstallmentResource($installment->load(PurchaseInvoiceInstallmentResource::RELATIONS)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['installment' => $installment->id]);
        }
    }

    /**
     * PATCH /api/purchase-invoice-installments/{installment}
     */
    public function update(UpdatePurchaseInvoiceInstallmentRequest $request, PurchaseInvoiceInstallment $installment): JsonResponse
    {
        try {
            $updated = $this->updater->handle($installment, $request->toData());

            return $this->ok(new PurchaseInvoiceInstallmentResource($updated), 'Installment updated.');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['installment' => $installment->id]);
        }
    }
}
