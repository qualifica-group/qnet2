<?php

namespace App\Http\Controllers\ChartOfAccounts;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ChartOfAccounts\StoreTreasuryTypeRequest;
use App\Http\Requests\ChartOfAccounts\UpdateTreasuryTypeRequest;
use App\Http\Resources\TreasuryTypeResource;
use App\Models\TreasuryType;
use App\Services\ChartOfAccounts\TreasuryTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the `treasury-types` resource (spec 0360): the financial
 * sub-accounts. Thin controller (TreasuryTypePolicy + Service).
 *
 * @see TreasuryTypeService
 */
class TreasuryTypeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly TreasuryTypeService $service) {}

    public function show(TreasuryType $treasuryType): JsonResponse
    {
        try {
            $this->authorize('view', $treasuryType);

            return $this->ok(new TreasuryTypeResource($treasuryType));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['treasuryType' => $treasuryType->id]);
        }
    }

    public function store(StoreTreasuryTypeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TreasuryType::class);

            $treasuryType = $this->service->create($request->validated());

            return $this->ok(new TreasuryTypeResource($treasuryType), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateTreasuryTypeRequest $request, TreasuryType $treasuryType): JsonResponse
    {
        try {
            $this->authorize('update', $treasuryType);

            $treasuryType = $this->service->update($treasuryType, $request->validated());

            return $this->ok(new TreasuryTypeResource($treasuryType));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['treasuryType' => $treasuryType->id]);
        }
    }

    public function destroy(TreasuryType $treasuryType): JsonResponse
    {
        try {
            $this->authorize('delete', $treasuryType);

            $this->service->delete($treasuryType);

            return $this->ok(null);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['treasuryType' => $treasuryType->id]);
        }
    }
}
