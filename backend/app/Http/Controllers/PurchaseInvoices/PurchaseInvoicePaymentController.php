<?php

namespace App\Http\Controllers\PurchaseInvoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseInvoices\PurchaseInvoicePaymentRequest;
use App\Http\Resources\PurchaseInvoiceInstallmentResource;
use App\Models\PurchaseInvoiceInstallment;
use App\Services\PurchaseInvoices\PurchaseInvoicePaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Record / clear the payment of one installment (spec 0211), gated by the
 * `pay` ability of the parent document.
 *
 * @see PurchaseInvoicePaymentService
 */
class PurchaseInvoicePaymentController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly PurchaseInvoicePaymentService $service) {}

    /**
     * PUT /api/purchase-invoice-installments/{installment}/payment
     */
    public function store(PurchaseInvoicePaymentRequest $request, PurchaseInvoiceInstallment $installment): JsonResponse
    {
        try {
            $paid = $this->service->record($installment, $request->payload());

            return $this->ok(new PurchaseInvoiceInstallmentResource($paid), 'Payment recorded.');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['installment' => $installment->id]);
        }
    }

    /**
     * DELETE /api/purchase-invoice-installments/{installment}/payment
     */
    public function destroy(PurchaseInvoiceInstallment $installment): JsonResponse
    {
        try {
            $installment->loadMissing('purchaseInvoice');
            $this->authorize('pay', $installment->purchaseInvoice);

            return $this->ok(new PurchaseInvoiceInstallmentResource($this->service->clear($installment)), 'Payment cleared.');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['installment' => $installment->id]);
        }
    }
}
