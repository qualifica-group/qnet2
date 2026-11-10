<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Products\SetProductsActivationRequest;
use App\Services\Products\ProductActivationService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Mass activation / deactivation of products (spec 0366). The gate
 * (`products.update` + `is_active` field permission) lives in
 * SetProductsActivationRequest::authorize().
 */
class ProductActivationController extends BaseApiController
{
    public function __construct(private readonly ProductActivationService $service) {}

    /**
     * POST /api/products/activation
     */
    public function __invoke(SetProductsActivationRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->service->apply($request->productIds(), $request->isActive()), 'Products updated');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
