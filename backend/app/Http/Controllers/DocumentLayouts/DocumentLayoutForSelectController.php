<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentLayouts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentLayouts\DocumentLayoutForSelectRequest;
use App\Http\Resources\DocumentLayoutForSelectResource;
use App\Models\DocumentLayout;
use App\Services\DocumentLayoutService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/document-layouts/for-select — minimal, searchable, paginated
 * document layout list scoped to one `module` (spec 0069, ADR 0011 the
 * for-select standard).
 *
 * Thin invokable controller: validation (DocumentLayoutForSelectRequest),
 * server-side authorization (document-layouts.viewAny via
 * DocumentLayoutPolicy), Service call, paginated response.
 *
 * @see DocumentLayoutService::forSelect
 */
class DocumentLayoutForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentLayoutService $service) {}

    public function __invoke(DocumentLayoutForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', DocumentLayout::class);

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
