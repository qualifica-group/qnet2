<?php

namespace App\Http\Controllers\CollectiveAgreements;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Shared\NamedCatalogForSelectRequest;
use App\Http\Resources\CollectiveAgreementLevelForSelectResource;
use App\Services\CollectiveAgreementService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/collective-agreement-levels/for-select (spec 0272, ADR 0011). No
 * permission gate beyond `auth:sanctum`: the option list feeds the contract
 * form of actors who may lack browse rights on the CCNL module.
 */
class CollectiveAgreementLevelForSelectController extends BaseApiController
{
    public function __construct(private readonly CollectiveAgreementService $service) {}

    public function __invoke(NamedCatalogForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->levelsForSelect($request->toData());

            return $this->paginatedResponse(
                CollectiveAgreementLevelForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
