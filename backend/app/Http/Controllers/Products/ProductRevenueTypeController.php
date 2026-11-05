<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Products\SetProductsRevenueTypeRequest;
use App\Services\Products\ProductRevenueTypeService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Mass default-revenue-type assignment on products (spec 0350). The
 * `products.update` gate lives in SetProductsRevenueTypeRequest::authorize().
 *
 * @see ProductRevenueTypeService
 */
class ProductRevenueTypeController extends BaseApiController
{
    public function __construct(private readonly ProductRevenueTypeService $service) {}

    /**
     * POST /api/products/revenue-type
     */
    public function assign(SetProductsRevenueTypeRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->service->assign($request->productIds(), $request->revenueTypeId()), 'Revenue type updated');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
