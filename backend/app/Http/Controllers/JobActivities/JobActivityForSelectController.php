<?php

namespace App\Http\Controllers\JobActivities;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Shared\NamedCatalogForSelectRequest;
use App\Http\Resources\NamedCatalogForSelectResource;
use App\Services\JobActivityService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/job-activities/for-select (spec 0271, ADR 0011). No permission gate beyond
 * `auth:sanctum`: option lists feed forms whose actor may lack browse rights
 * on the source module.
 */
class JobActivityForSelectController extends BaseApiController
{
    public function __construct(private readonly JobActivityService $service) {}

    public function __invoke(NamedCatalogForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                NamedCatalogForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
