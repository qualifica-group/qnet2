<?php

namespace App\Http\Controllers\PurchaseInvoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseInvoices\InvoiceableOrdersRequest;
use App\Services\PurchaseInvoices\PurchaseInvoiceDraftBuilder;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/purchase-invoices/invoiceable-orders: the purchase orders of a
 * supplier and company that can still be invoiced (spec 0250, D-1).
 */
class PurchaseInvoiceInvoiceableOrdersController extends BaseApiController
{
    public function __construct(private readonly PurchaseInvoiceDraftBuilder $builder) {}

    public function __invoke(InvoiceableOrdersRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->builder->invoiceableOrders(
                (int) $request->validated('supplier_id'),
                (int) $request->validated('company_id'),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
