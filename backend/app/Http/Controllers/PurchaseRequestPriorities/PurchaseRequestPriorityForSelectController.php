<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseRequestPriorities;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseRequestPriorities\PurchaseRequestPriorityForSelectRequest;
use App\Http\Resources\PurchaseRequestPriorityForSelectResource;
use App\Services\PurchaseRequestPriorityService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/purchase-request-priorities/for-select — minimal, searchable, paginated purchase request priority
 * list feeding entity-backed selects (spec 0101, ADR 0011).
 *
 * Thin invokable controller: validation (PurchaseRequestPriorityForSelectRequest), Service
 * call, paginated response. No permission gate beyond `auth:sanctum` (ADR
 * 0011, amended 2026-07-31 — AC-051): the purchase request form needs this list from an
 * actor who may legitimately lack browse rights on the configurator module
 * itself.
 *
 * @see PurchaseRequestPriorityService::forSelect
 */
class PurchaseRequestPriorityForSelectController extends BaseApiController
{
    public function __construct(private readonly PurchaseRequestPriorityService $service) {}

    public function __invoke(PurchaseRequestPriorityForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                PurchaseRequestPriorityForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
