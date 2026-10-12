<?php

namespace App\Http\Controllers\CostTypes;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CostTypes\StoreCostTypeRequest;
use App\Http\Requests\CostTypes\UpdateCostTypeRequest;
use App\Http\Resources\CostTypeResource;
use App\Models\CostType;
use App\Services\CostTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the `cost-types` resource (spec 0210). Thin controller:
 * validation (FormRequest), authorization (CostTypePolicy), Service call, response.
 *
 * @see CostTypeService
 */
class CostTypeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CostTypeService $service) {}

    public function show(CostType $costType): JsonResponse
    {
        try {
            $this->authorize('view', $costType);

            return $this->ok(new CostTypeResource($costType));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['costType' => $costType->id]);
        }
    }

    public function store(StoreCostTypeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', CostType::class);

            $costType = $this->service->create($request->toData());

            return $this->ok(new CostTypeResource($costType), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateCostTypeRequest $request, CostType $costType): JsonResponse
    {
        try {
            $this->authorize('update', $costType);

            $costType = $this->service->update($costType, $request->toData());

            return $this->ok(new CostTypeResource($costType));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['costType' => $costType->id]);
        }
    }

    public function destroy(CostType $costType): JsonResponse
    {
        try {
            $this->authorize('delete', $costType);

            $this->service->delete($costType);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['costType' => $costType->id]);
        }
    }
}
