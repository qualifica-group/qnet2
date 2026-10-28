<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Products\ProductSupplierCommissionsRequest;
use App\Products\ProductSupplierPayload;
use App\Products\SupplierCommissionLookup;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/products/supplier-commissions — which of the given suppliers carry
 * a Supplier commission on a product today (spec 0320, D-21/D-22). The
 * permission gate lives in ProductSupplierCommissionsRequest::authorize().
 *
 * @see SupplierCommissionLookup
 */
class ProductSupplierCommissionsController extends BaseApiController
{
    public function __construct(private readonly SupplierCommissionLookup $lookup) {}

    public function __invoke(ProductSupplierCommissionsRequest $request): JsonResponse
    {
        try {
            $rules = $this->lookup->forSuppliers(
                $request->supplierIds(),
                $request->productId(),
                $request->categoryId(),
                $request->productTypologyId(),
            );

            $data = [];

            foreach ($request->supplierIds() as $supplierId) {
                $data[] = [
                    'supplier_id' => $supplierId,
                    'commission' => ProductSupplierPayload::commission($rules[$supplierId] ?? null),
                ];
            }

            return $this->ok($data);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
