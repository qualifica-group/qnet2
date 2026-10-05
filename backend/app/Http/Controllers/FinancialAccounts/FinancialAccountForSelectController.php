<?php

namespace App\Http\Controllers\FinancialAccounts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\FinancialAccounts\FinancialAccountForSelectRequest;
use App\Http\Resources\FinancialAccountForSelectResource;
use App\Services\FinancialAccountService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/financial-accounts/for-select — minimal, searchable, paginated
 * account list feeding entity-backed selects (spec 0189, ADR 0011). No
 * permission gate beyond `auth:sanctum` (ADR 0011, amended 2026-07-31).
 *
 * @see FinancialAccountService::forSelect
 */
class FinancialAccountForSelectController extends BaseApiController
{
    public function __construct(private readonly FinancialAccountService $service) {}

    public function __invoke(FinancialAccountForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData(), $request->accountType());

            return $this->paginatedResponse(
                FinancialAccountForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
                hasMore: $result->hasMore,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
