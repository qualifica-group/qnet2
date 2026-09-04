<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tasks\TaskForSelectRequest;
use App\Http\Resources\TaskForSelectResource;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/tasks/for-select — minimal, searchable, paginated Task list
 * feeding entity-backed selects (ADR 0011), mirroring
 * ReferentForSelectController. First producer: the Task form's own
 * "Task padre" picker, which passes `exclude_id` so a Task is never offered
 * as its own parent (AC-082).
 *
 * Thin invokable controller: validation (TaskForSelectRequest), Service
 * call, paginated response. No permission gate beyond `auth:sanctum` (ADR
 * 0011, amended 2026-07-31) — but the rows ARE restricted by
 * TaskVisibilityScope inside the Service (D-9), because that is a security
 * boundary and not a browse convenience.
 *
 * @see TaskService::forSelect
 */
class TaskForSelectController extends BaseApiController
{
    public function __construct(private readonly TaskService $service) {}

    public function __invoke(TaskForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData(), $request->excludeId());

            return $this->paginatedResponse(
                TaskForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
