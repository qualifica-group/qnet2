<?php

namespace App\Http\Controllers\StaffCourses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Shared\NamedCatalogForSelectRequest;
use App\Http\Resources\NamedCatalogForSelectResource;
use App\Services\StaffCourseService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/staff-courses/for-select (spec 0284, ADR 0011): active courses. No
 * permission gate beyond `auth:sanctum`: option lists feed forms whose actor
 * may lack browse rights on the catalogue.
 */
class StaffCourseForSelectController extends BaseApiController
{
    public function __construct(private readonly StaffCourseService $service) {}

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
