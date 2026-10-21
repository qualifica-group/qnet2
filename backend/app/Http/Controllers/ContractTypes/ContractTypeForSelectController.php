<?php

namespace App\Http\Controllers\ContractTypes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Shared\NamedCatalogForSelectRequest;
use App\Http\Resources\ContractTypeForSelectResource;
use App\Services\ContractTypeService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/contract-types/for-select (spec 0290, ADR 0011). No permission
 * gate beyond `auth:sanctum`: the option list feeds the contract form of
 * actors who may lack browse rights on the catalogue.
 */
class ContractTypeForSelectController extends BaseApiController
{
    public function __construct(private readonly ContractTypeService $service) {}

    public function __invoke(NamedCatalogForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ContractTypeForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
