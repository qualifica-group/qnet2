<?php

namespace App\Http\Controllers\Referents;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Referents\ReferentForSelectRequest;
use App\Http\Resources\ReferentForSelectResource;
use App\Services\ReferentService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/referents/for-select — minimal, searchable, paginated referent
 * list feeding entity-backed selects (spec 0020, ADR 0011 the for-select
 * standard), mirroring SourceForSelectController. First producer: the
 * Registries form's "Referenti per azienda"/supervisor/commercial/reporter
 * selects.
 *
 * Thin invokable controller: validation (ReferentForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see ReferentService::forSelect
 */
class ReferentForSelectController extends BaseApiController
{
    public function __construct(private readonly ReferentService $service) {}

    public function __invoke(ReferentForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData(), $request->registryId());

            return $this->paginatedResponse(
                ReferentForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
