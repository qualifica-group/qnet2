<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Shared\DocumentForSelectRequest;
use App\Http\Resources\PurchaseOrderForSelectResource;
use App\Services\PurchaseOrders\PurchaseOrderForSelectService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/purchase-orders/for-select — minimal, searchable, paginated list feeding
 * entity-backed selects and the AI assistant mentions (ADR 0011, spec 0347).
 * No gate beyond `auth:sanctum` (ADR 0011, amended 2026-07-31); the rows are
 * restricted inside the Service exactly like the module's index.
 *
 * @see PurchaseOrderForSelectService::forSelect
 */
class PurchaseOrderForSelectController extends BaseApiController
{
    public function __construct(private readonly PurchaseOrderForSelectService $service) {}

    public function __invoke(DocumentForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                PurchaseOrderForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
                hasMore: $result->hasMore,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
