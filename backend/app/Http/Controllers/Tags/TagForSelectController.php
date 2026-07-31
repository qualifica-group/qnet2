<?php

namespace App\Http\Controllers\Tags;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tags\TagForSelectRequest;
use App\Http\Resources\TagForSelectResource;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/tags/for-select — minimal, searchable, paginated tag list
 * feeding entity-backed selects (spec 0019, ADR 0011 the for-select
 * standard), mirroring SourceForSelectController.
 *
 * Thin invokable controller: validation (TagForSelectRequest), Service
 * call, paginated response. No permission gate beyond `auth:sanctum`
 * (ADR 0011, amended 2026-07-31): option lists feed forms whose actor
 * may legitimately lack browse rights on the source module.
 *
 * @see TagService::forSelect
 */
class TagForSelectController extends BaseApiController
{
    public function __construct(private readonly TagService $service) {}

    public function __invoke(TagForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                TagForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
