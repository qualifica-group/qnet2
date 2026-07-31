<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Products\ProductForSelectRequest;
use App\Http\Resources\ProductForSelectResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/products/for-select — minimal, searchable, paginated product list
 * feeding entity-backed selects (ADR 0011 the for-select standard),
 * mirroring ProductCategoryForSelectController. Feeds the "prodotti di
 * interesse" picker of the opportunity form and of the request-management
 * work panel, optionally scoped by `category_ids[]`.
 *
 * Thin invokable controller: validation (ProductForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see ProductService::forSelect
 */
class ProductForSelectController extends BaseApiController
{
    public function __construct(private readonly ProductService $service) {}

    public function __invoke(ProductForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ProductForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
