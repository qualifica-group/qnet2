<?php

namespace App\Http\Controllers\ProductCategories;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProductCategories\ProductCategoryForSelectRequest;
use App\Http\Resources\ProductCategoryForSelectResource;
use App\Services\ProductCategoryService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/product-categories/for-select — minimal, searchable, paginated
 * product-category list feeding entity-backed selects (spec 0023, ADR 0011
 * the for-select standard), mirroring SourceForSelectController.
 *
 * Thin invokable controller: validation
 * (ProductCategoryForSelectRequest), Service call, paginated response.
 * No permission gate beyond `auth:sanctum` (ADR 0011, amended
 * 2026-07-31): option lists feed forms whose actor may legitimately
 * lack browse rights on the source module.
 *
 * @see ProductCategoryService::forSelect
 */
class ProductCategoryForSelectController extends BaseApiController
{
    public function __construct(private readonly ProductCategoryService $service) {}

    public function __invoke(ProductCategoryForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ProductCategoryForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
