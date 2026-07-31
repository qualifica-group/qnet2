<?php

namespace App\Http\Controllers\Sectors;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Sectors\SectorForSelectRequest;
use App\Http\Resources\SectorForSelectResource;
use App\Services\SectorService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/sectors/for-select — minimal, searchable, paginated sector
 * list feeding entity-backed selects (spec 0020, ADR 0011 the for-select
 * standard), mirroring SourceForSelectController. First producer: the
 * Registries form's "Settore EA / Competenze" multiselect (sectors
 * previously only exposed the `tree` read view).
 *
 * Thin invokable controller: validation (SectorForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see SectorService::forSelect
 */
class SectorForSelectController extends BaseApiController
{
    public function __construct(private readonly SectorService $service) {}

    public function __invoke(SectorForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                SectorForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
