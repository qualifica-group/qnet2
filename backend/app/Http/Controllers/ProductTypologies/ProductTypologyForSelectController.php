<?php

namespace App\Http\Controllers\ProductTypologies;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProductTypologies\ProductTypologyForSelectRequest;
use App\Http\Resources\ProductTypologyForSelectResource;
use App\Services\ProductTypologyService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/product-typologies/for-select — minimal, searchable, paginated
 * typology list feeding entity-backed selects (spec 0099, ADR 0011 the
 * for-select standard).
 *
 * Thin invokable controller: validation (ProductTypologyForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed forms
 * whose actor may legitimately lack browse rights on the source module —
 * which is also why the Offer summary (D-7) can list every configured
 * typology for an operator who cannot open the typologies module itself.
 *
 * @see ProductTypologyService::forSelect
 */
class ProductTypologyForSelectController extends BaseApiController
{
    public function __construct(private readonly ProductTypologyService $service) {}

    public function __invoke(ProductTypologyForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ProductTypologyForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
