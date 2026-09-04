<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskPriorities;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TaskPriorities\TaskPriorityForSelectRequest;
use App\Http\Resources\TaskPriorityForSelectResource;
use App\Services\TaskPriorityService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/task-priorities/for-select — minimal, searchable, paginated task priority
 * list feeding entity-backed selects (spec 0101, ADR 0011).
 *
 * Thin invokable controller: validation (TaskPriorityForSelectRequest), Service
 * call, paginated response. No permission gate beyond `auth:sanctum` (ADR
 * 0011, amended 2026-07-31 — AC-051): the Task form needs this list from an
 * actor who may legitimately lack browse rights on the configurator module
 * itself.
 *
 * @see TaskPriorityService::forSelect
 */
class TaskPriorityForSelectController extends BaseApiController
{
    public function __construct(private readonly TaskPriorityService $service) {}

    public function __invoke(TaskPriorityForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                TaskPriorityForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
