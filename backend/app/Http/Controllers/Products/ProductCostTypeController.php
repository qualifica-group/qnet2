<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Products\SetProductsCostTypeRequest;
use App\Services\Products\ProductCostTypeService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Mass default-cost-type assignment on products (spec 0251, D-6). The
 * `products.update` gate lives in SetProductsCostTypeRequest::authorize().
 *
 * @see ProductCostTypeService
 */
class ProductCostTypeController extends BaseApiController
{
    public function __construct(private readonly ProductCostTypeService $service) {}

    /**
     * POST /api/products/cost-type
     */
    public function assign(SetProductsCostTypeRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->service->assign($request->productIds(), $request->costTypeId()), 'Cost type updated');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
