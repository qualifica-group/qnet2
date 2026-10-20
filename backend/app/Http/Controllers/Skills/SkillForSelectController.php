<?php

namespace App\Http\Controllers\Skills;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Shared\NamedCatalogForSelectRequest;
use App\Http\Resources\SkillForSelectResource;
use App\Services\SkillService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/skills/for-select (spec 0286, ADR 0011): active skills. No
 * permission gate beyond `auth:sanctum`: option lists feed forms whose actor
 * may lack browse rights on the catalogue.
 */
class SkillForSelectController extends BaseApiController
{
    public function __construct(private readonly SkillService $service) {}

    public function __invoke(NamedCatalogForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                SkillForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
