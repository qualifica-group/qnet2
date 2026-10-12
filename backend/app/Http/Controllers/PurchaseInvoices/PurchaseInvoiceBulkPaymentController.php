<?php

namespace App\Http\Controllers\PurchaseInvoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseInvoices\PurchaseInvoiceBulkPaymentRequest;
use App\Services\PurchaseInvoices\PurchaseInvoiceBulkPayer;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Pay several installments of one supplier at once (spec 0211, D-8), gated by
 * purchase-invoices.pay on the request and on every document involved.
 *
 * @see PurchaseInvoiceBulkPayer
 */
class PurchaseInvoiceBulkPaymentController extends BaseApiController
{
    public function __construct(private readonly PurchaseInvoiceBulkPayer $payer) {}

    /**
     * POST /api/purchase-invoice-installments/payments
     */
    public function __invoke(PurchaseInvoiceBulkPaymentRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->payer->handle($request->user(), $request->items(), $request->paidAt(), $request->financialAccountId()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
