<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TaskStatuses\TaskStatusForSelectRequest;
use App\Http\Resources\TaskStatusForSelectResource;
use App\Services\TaskStatusService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/task-statuses/for-select — minimal, searchable, paginated task status
 * list feeding entity-backed selects (spec 0101, ADR 0011).
 *
 * Thin invokable controller: validation (TaskStatusForSelectRequest), Service
 * call, paginated response. No permission gate beyond `auth:sanctum` (ADR
 * 0011, amended 2026-07-31 — AC-051): the Task form needs this list from an
 * actor who may legitimately lack browse rights on the configurator module
 * itself.
 *
 * @see TaskStatusService::forSelect
 */
class TaskStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly TaskStatusService $service) {}

    public function __invoke(TaskStatusForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                TaskStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
