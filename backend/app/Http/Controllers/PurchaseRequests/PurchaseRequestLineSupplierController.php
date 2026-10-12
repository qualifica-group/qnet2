<?php

namespace App\Http\Controllers\PurchaseRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseRequests\PurchaseRequestLineSupplierRequest;
use App\Services\PurchaseRequests\PurchaseRequestLineSupplierService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Mass supplier assignment on purchase request lines (spec 0209, D-4). Like the
 * status change it has no Policy ability of its own: the service checks, per
 * line, the actor's capabilities and update right on the request.
 *
 * @see PurchaseRequestLineSupplierService
 */
class PurchaseRequestLineSupplierController extends BaseApiController
{
    public function __construct(private readonly PurchaseRequestLineSupplierService $service) {}

    /**
     * POST /api/purchase-request-lines/supplier
     */
    public function assign(PurchaseRequestLineSupplierRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->service->assign($request->user(), $request->lineIds(), $request->supplierId()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
