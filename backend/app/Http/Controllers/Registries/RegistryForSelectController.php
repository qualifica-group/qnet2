<?php

namespace App\Http\Controllers\Registries;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Registries\RegistryForSelectRequest;
use App\Http\Resources\RegistryForSelectResource;
use App\Services\RegistryService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/registries/for-select — minimal, searchable, paginated registry
 * list feeding entity-backed selects (spec 0023, ADR 0011 the for-select
 * standard), mirroring SourceForSelectController.
 *
 * Thin invokable controller: validation (RegistryForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see RegistryService::forSelect
 */
class RegistryForSelectController extends BaseApiController
{
    public function __construct(private readonly RegistryService $service) {}

    public function __invoke(RegistryForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData(), $request->boolean('is_supplier'));

            return $this->paginatedResponse(
                RegistryForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
