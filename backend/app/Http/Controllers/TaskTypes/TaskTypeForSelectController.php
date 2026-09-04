<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskTypes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TaskTypes\TaskTypeForSelectRequest;
use App\Http\Resources\TaskTypeForSelectResource;
use App\Services\TaskTypeService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/task-types/for-select — minimal, searchable, paginated task type
 * list feeding entity-backed selects (spec 0101, ADR 0011).
 *
 * Thin invokable controller: validation (TaskTypeForSelectRequest), Service
 * call, paginated response. No permission gate beyond `auth:sanctum` (ADR
 * 0011, amended 2026-07-31 — AC-051): the Task form needs this list from an
 * actor who may legitimately lack browse rights on the configurator module
 * itself.
 *
 * @see TaskTypeService::forSelect
 */
class TaskTypeForSelectController extends BaseApiController
{
    public function __construct(private readonly TaskTypeService $service) {}

    public function __invoke(TaskTypeForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                TaskTypeForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
