<?php

namespace App\Http\Controllers\ChartOfAccounts;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ChartOfAccounts\StoreRevenueTypeRequest;
use App\Http\Requests\ChartOfAccounts\UpdateRevenueTypeRequest;
use App\Http\Resources\RevenueTypeResource;
use App\Models\RevenueType;
use App\Services\ChartOfAccounts\RevenueTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the `revenue-types` resource (spec 0348): the revenue
 * sub-accounts. Thin controller (RevenueTypePolicy + Service).
 *
 * @see RevenueTypeService
 */
class RevenueTypeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly RevenueTypeService $service) {}

    public function show(RevenueType $revenueType): JsonResponse
    {
        try {
            $this->authorize('view', $revenueType);

            return $this->ok(new RevenueTypeResource($revenueType));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['revenueType' => $revenueType->id]);
        }
    }

    public function store(StoreRevenueTypeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', RevenueType::class);

            $revenueType = $this->service->create($request->validated());

            return $this->ok(new RevenueTypeResource($revenueType), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateRevenueTypeRequest $request, RevenueType $revenueType): JsonResponse
    {
        try {
            $this->authorize('update', $revenueType);

            $revenueType = $this->service->update($revenueType, $request->validated());

            return $this->ok(new RevenueTypeResource($revenueType));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['revenueType' => $revenueType->id]);
        }
    }

    public function destroy(RevenueType $revenueType): JsonResponse
    {
        try {
            $this->authorize('delete', $revenueType);

            $this->service->delete($revenueType);

            return $this->ok(null);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['revenueType' => $revenueType->id]);
        }
    }
}
