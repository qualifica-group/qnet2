<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskCategories;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TaskCategories\TaskCategoryForSelectRequest;
use App\Http\Resources\TaskCategoryForSelectResource;
use App\Services\TaskCategoryService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/task-categories/for-select — minimal, searchable, paginated task category
 * list feeding entity-backed selects (spec 0101, ADR 0011).
 *
 * Thin invokable controller: validation (TaskCategoryForSelectRequest), Service
 * call, paginated response. No permission gate beyond `auth:sanctum` (ADR
 * 0011, amended 2026-07-31 — AC-051): the Task form needs this list from an
 * actor who may legitimately lack browse rights on the configurator module
 * itself.
 *
 * @see TaskCategoryService::forSelect
 */
class TaskCategoryForSelectController extends BaseApiController
{
    public function __construct(private readonly TaskCategoryService $service) {}

    public function __invoke(TaskCategoryForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                TaskCategoryForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
