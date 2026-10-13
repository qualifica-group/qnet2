<?php

namespace App\Http\Controllers\CommissionSettlements;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CommissionSettlements\CommissionAdjustmentRequest;
use App\Http\Resources\CommissionAdjustmentResource;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Services\Commissions\Settlements\CommissionAdjustmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of the manual commission adjustments (spec 0213, D-7), all
 * guarded by `commission-settlements.adjust`. The bound movement of PUT/DELETE
 * is an ordinary ledger row: the Service answers 404 when it is not an
 * ADJUSTMENT and 409 when its settlement is no longer a draft.
 *
 * @see CommissionAdjustmentService
 */
class CommissionAdjustmentController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CommissionAdjustmentService $service) {}

    /**
     * POST /api/commission-adjustments
     */
    public function store(CommissionAdjustmentRequest $request): JsonResponse
    {
        try {
            $this->authorize('adjust', CommissionSettlement::class);

            $adjustment = $this->service->create($request->toData(), $request->user());

            return $this->ok(new CommissionAdjustmentResource($adjustment), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/commission-adjustments/{commissionAccrual}
     */
    public function update(CommissionAdjustmentRequest $request, CommissionAccrual $commissionAccrual): JsonResponse
    {
        try {
            $this->authorize('adjust', CommissionSettlement::class);

            return $this->ok(new CommissionAdjustmentResource($this->service->update($commissionAccrual, $request->toData(), $request->user())));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionAccrual' => $commissionAccrual->id]);
        }
    }

    /**
     * DELETE /api/commission-adjustments/{commissionAccrual}
     */
    public function destroy(Request $request, CommissionAccrual $commissionAccrual): JsonResponse
    {
        try {
            $this->authorize('adjust', CommissionSettlement::class);

            $this->service->delete($commissionAccrual, $request->user());

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionAccrual' => $commissionAccrual->id]);
        }
    }
}
