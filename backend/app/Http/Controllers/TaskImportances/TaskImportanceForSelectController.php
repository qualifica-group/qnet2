<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskImportances;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TaskImportances\TaskImportanceForSelectRequest;
use App\Http\Resources\TaskImportanceForSelectResource;
use App\Services\TaskImportanceService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/task-importances/for-select — minimal, searchable, paginated task importance
 * list feeding entity-backed selects (spec 0101, ADR 0011).
 *
 * Thin invokable controller: validation (TaskImportanceForSelectRequest), Service
 * call, paginated response. No permission gate beyond `auth:sanctum` (ADR
 * 0011, amended 2026-07-31 — AC-051): the Task form needs this list from an
 * actor who may legitimately lack browse rights on the configurator module
 * itself.
 *
 * @see TaskImportanceService::forSelect
 */
class TaskImportanceForSelectController extends BaseApiController
{
    public function __construct(private readonly TaskImportanceService $service) {}

    public function __invoke(TaskImportanceForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                TaskImportanceForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
