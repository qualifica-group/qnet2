<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentLayouts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentLayouts\DocumentLayoutForSelectRequest;
use App\Http\Resources\DocumentLayoutForSelectResource;
use App\Services\DocumentLayoutService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/document-layouts/for-select — minimal, searchable, paginated
 * document layout list scoped to one `module` (spec 0069, ADR 0011 the
 * for-select standard).
 *
 * Thin invokable controller: validation
 * (DocumentLayoutForSelectRequest), Service call, paginated response.
 * No permission gate beyond `auth:sanctum` (ADR 0011, amended
 * 2026-07-31): option lists feed forms whose actor may legitimately
 * lack browse rights on the source module.
 *
 * @see DocumentLayoutService::forSelect
 */
class DocumentLayoutForSelectController extends BaseApiController
{
    public function __construct(private readonly DocumentLayoutService $service) {}

    public function __invoke(DocumentLayoutForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->module(), $request->toData());

            return $this->paginatedResponse(
                DocumentLayoutForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
