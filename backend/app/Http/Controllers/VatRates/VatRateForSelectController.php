<?php

namespace App\Http\Controllers\VatRates;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\VatRates\VatRateForSelectRequest;
use App\Http\Resources\VatRateForSelectResource;
use App\Services\VatRateService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/vat-rates/for-select — minimal, searchable, paginated VAT rate
 * list feeding entity-backed selects (ADR 0011 the for-select standard),
 * mirroring SourceForSelectController.
 *
 * Thin invokable controller: validation (VatRateForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see VatRateService::forSelect
 */
class VatRateForSelectController extends BaseApiController
{
    public function __construct(private readonly VatRateService $service) {}

    public function __invoke(VatRateForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                VatRateForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
