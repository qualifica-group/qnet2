<?php

namespace App\Http\Controllers\ProductCategories;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProductCategories\ProductCategoryRootForSelectRequest;
use App\Http\Resources\ProductCategoryBranchForSelectResource;
use App\Services\ProductCategories\ProductCategoryForSelectResolver;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/product-category-roots/for-select — the first-level product
 * categories, feeding the "Categoria madre" grid filter.
 *
 * Same `{id, label}` shape as the branch picker, so it reuses its resource. No permission gate beyond `auth:sanctum` (ADR 0011,
 * amended 2026-07-31).
 *
 * @see ProductCategoryForSelectResolver::resolveRoots
 */
class ProductCategoryRootForSelectController extends BaseApiController
{
    public function __construct(private readonly ProductCategoryForSelectResolver $resolver) {}

    public function __invoke(ProductCategoryRootForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->resolver->resolveRoots($request->toData(), $request->currentOnly(), $request->hideLinkedLegacy());

            return $this->paginatedResponse(
                ProductCategoryBranchForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
