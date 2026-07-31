<?php

namespace App\Http\Controllers\PipelineStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PipelineStatuses\PipelineStatusForSelectRequest;
use App\Http\Resources\PipelineStatusForSelectResource;
use App\Services\PipelineStatusService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/pipeline-statuses/for-select — minimal, searchable, paginated
 * project status list feeding entity-backed selects (spec 0023, ADR 0011 the
 * for-select standard), mirroring SourceForSelectController.
 *
 * Thin invokable controller: validation
 * (PipelineStatusForSelectRequest), Service call, paginated response.
 * No permission gate beyond `auth:sanctum` (ADR 0011, amended
 * 2026-07-31): option lists feed forms whose actor may legitimately
 * lack browse rights on the source module.
 *
 * @see PipelineStatusService::forSelect
 */
class PipelineStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly PipelineStatusService $service) {}

    public function __invoke(PipelineStatusForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                PipelineStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
