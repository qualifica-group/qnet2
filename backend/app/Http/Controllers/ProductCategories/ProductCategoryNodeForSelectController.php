<?php

namespace App\Http\Controllers\ProductCategories;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProductCategories\ProductCategoryBranchForSelectRequest;
use App\Http\Resources\ProductCategoryBranchForSelectResource;
use App\Services\ProductCategories\ProductCategoryForSelectResolver;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/product-category-nodes/for-select — every active product category,
 * containers included, `is_selectable` ignored (spec 0223 D-5): the commission
 * configurator's category, whose rule covers the whole branch.
 *
 * Same query and `{id, label}` shape as the branch picker, so it reuses its
 * request and resource; the scope lives in
 * ProductCategoryForSelectResolver::resolveNodes. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31).
 *
 * @see ProductCategoryForSelectResolver::resolveNodes
 */
class ProductCategoryNodeForSelectController extends BaseApiController
{
    public function __construct(private readonly ProductCategoryForSelectResolver $resolver) {}

    public function __invoke(ProductCategoryBranchForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->resolver->resolveNodes($request->toData());

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
