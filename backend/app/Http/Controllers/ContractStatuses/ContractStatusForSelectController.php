<?php

declare(strict_types=1);

namespace App\Http\Controllers\ContractStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ContractStatuses\ContractStatusForSelectRequest;
use App\Http\Resources\ContractStatusForSelectResource;
use App\Services\ContractStatusService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/contract-statuses/for-select — minimal, searchable, paginated
 * contract status list feeding entity-backed selects (spec 0072, ADR 0011
 * the for-select standard).
 *
 * Thin invokable controller: validation (ContractStatusForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31 — AC-028): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see ContractStatusService::forSelect
 */
class ContractStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly ContractStatusService $service) {}

    public function __invoke(ContractStatusForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ContractStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
