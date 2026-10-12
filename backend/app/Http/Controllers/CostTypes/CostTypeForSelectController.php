<?php

namespace App\Http\Controllers\CostTypes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CostTypes\CostTypeForSelectRequest;
use App\Http\Resources\CostTypeForSelectResource;
use App\Services\CostTypeService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/cost-types/for-select: active cost types feeding entity-backed
 * selects (spec 0210, ADR 0011). No permission gate beyond `auth:sanctum`:
 * option lists feed forms whose actor may lack browse rights on the source module.
 */
class CostTypeForSelectController extends BaseApiController
{
    public function __construct(private readonly CostTypeService $service) {}

    public function __invoke(CostTypeForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                CostTypeForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
