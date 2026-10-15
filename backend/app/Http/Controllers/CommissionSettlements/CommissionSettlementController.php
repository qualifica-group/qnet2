<?php

namespace App\Http\Controllers\CommissionSettlements;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CommissionSettlements\CommissionSettlementCancelRequest;
use App\Http\Requests\CommissionSettlements\CommissionSettlementNotesRequest;
use App\Models\CommissionSettlement;
use App\Services\Commissions\Settlements\CommissionSettlementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of a commission settlement (spec 0213): detail, notes, the state
 * transitions and the draft removal. Thin controller: FormRequest, server-side
 * authorization (CommissionSettlementPolicy), Service call, response. The state
 * rules (409) and the recalculation errors (422) belong to the Service.
 *
 * @see CommissionSettlementService
 */
class CommissionSettlementController extends BaseApiController
{
    use AuthorizesRequests, RespondsWithSettlement;

    public function __construct(private readonly CommissionSettlementService $service) {}

    /**
     * GET /api/commission-settlements/{commissionSettlement}
     */
    public function show(Request $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            $this->authorize('view', $commissionSettlement);

            return $this->respond($request->user(), $this->service->detail($commissionSettlement));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }

    /**
     * PATCH /api/commission-settlements/{commissionSettlement}
     */
    public function update(CommissionSettlementNotesRequest $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            $this->authorize('update', $commissionSettlement);

            return $this->respond($request->user(), $this->service->updateNotes($commissionSettlement, $request->notes()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }

    /**
     * POST /api/commission-settlements/{commissionSettlement}/recalculate
     */
    public function recalculate(Request $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            $this->authorize('update', $commissionSettlement);

            return $this->respond($request->user(), $this->service->recalculate($commissionSettlement));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }

    /**
     * POST /api/commission-settlements/{commissionSettlement}/issue
     */
    public function issue(Request $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            $this->authorize('issue', $commissionSettlement);

            return $this->respond($request->user(), $this->service->issue($commissionSettlement, $request->user()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }

    /**
     * POST /api/commission-settlements/{commissionSettlement}/cancel
     */
    public function cancel(CommissionSettlementCancelRequest $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            $this->authorize('cancel', $commissionSettlement);

            return $this->respond($request->user(), $this->service->cancel($commissionSettlement, $request->user(), $request->reason()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }

    /**
     * DELETE /api/commission-settlements/{commissionSettlement}
     */
    public function destroy(CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            $this->authorize('delete', $commissionSettlement);

            $this->service->delete($commissionSettlement);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }
}
