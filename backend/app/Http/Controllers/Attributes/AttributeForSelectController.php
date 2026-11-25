<?php

namespace App\Http\Controllers\Attributes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Attributes\AttributeForSelectRequest;
use App\Http\Resources\AttributeForSelectResource;
use App\Services\Attributes\AttributeForSelectService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/attributes/for-select — the "Campo attuale" picker of the
 * attribute form (spec 0429 D-7, ADR 0011). No gate beyond `auth:sanctum`.
 */
class AttributeForSelectController extends BaseApiController
{
    public function __construct(private readonly AttributeForSelectService $service) {}

    public function __invoke(AttributeForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData(), $request->excludeId());

            return $this->paginatedResponse(
                AttributeForSelectResource::collection($result->items),
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
