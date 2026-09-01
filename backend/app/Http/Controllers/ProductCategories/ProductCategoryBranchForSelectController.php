<?php

namespace App\Http\Controllers\ProductCategories;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProductCategories\ProductCategoryBranchForSelectRequest;
use App\Http\Resources\ProductCategoryBranchForSelectResource;
use App\Services\ProductCategories\ProductCategoryForSelectResolver;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/product-category-branches/for-select — the container categories a
 * quote-workflow BRANCH criterion may point at (spec 0092 D-4): those with at
 * least one child, `is_selectable` ignored. Feeds the criteria editor's value
 * select, which reaches it through the catalogue's
 * `for_select_resource: 'product-category-branches'`.
 *
 * Thin invokable controller mirroring ProductCategoryForSelectController: the
 * scope lives in ProductCategoryForSelectResolver::resolveBranches, not here.
 * No permission gate beyond `auth:sanctum` (ADR 0011, amended 2026-07-31).
 *
 * @see ProductCategoryForSelectResolver::resolveBranches
 */
class ProductCategoryBranchForSelectController extends BaseApiController
{
    public function __construct(private readonly ProductCategoryForSelectResolver $resolver) {}

    public function __invoke(ProductCategoryBranchForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->resolver->resolveBranches($request->toData());

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
