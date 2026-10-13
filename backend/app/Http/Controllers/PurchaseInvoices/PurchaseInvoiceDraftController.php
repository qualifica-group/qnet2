<?php

namespace App\Http\Controllers\PurchaseInvoices;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseInvoices\PurchaseInvoiceDraftRequest;
use App\Services\PurchaseInvoices\PurchaseInvoiceDraftBuilder;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/purchase-invoices/draft: defaults and pre-filled lines to register a
 * payable document from purchase orders (spec 0250, D-1). Authorization
 * (purchase-invoices.create) lives in the FormRequest.
 */
class PurchaseInvoiceDraftController extends BaseApiController
{
    public function __construct(private readonly PurchaseInvoiceDraftBuilder $builder) {}

    public function __invoke(PurchaseInvoiceDraftRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->builder->build($request->purchaseOrderIds()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
