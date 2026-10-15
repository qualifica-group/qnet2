<?php

namespace App\Http\Controllers\CommissionSettlements;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CommissionSettlements\SettlementApproveRequest;
use App\Http\Requests\CommissionSettlements\SettlementSupplierRequest;
use App\Models\CommissionSettlement;
use App\Services\Commissions\Settlements\AgentSupplierService;
use App\Services\Commissions\Settlements\SettlementApprovalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Approval of a settlement (spec 0267): the window data, the agent supplier
 * registry and the approval itself. Thin controller; the state rules (409) and
 * the business errors (422) belong to the Services.
 *
 * @see SettlementApprovalService
 * @see AgentSupplierService
 */
class SettlementApprovalController extends BaseApiController
{
    use AuthorizesRequests, RespondsWithSettlement;

    public function __construct(
        private readonly SettlementApprovalService $approval,
        private readonly AgentSupplierService $suppliers,
    ) {}

    /**
     * GET /api/commission-settlements/{commissionSettlement}/approval-draft
     */
    public function draft(Request $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            $this->authorize('approve', $commissionSettlement);

            return $this->ok($this->approval->draft($commissionSettlement, $request->user()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }

    /**
     * POST /api/commission-settlements/{commissionSettlement}/supplier
     */
    public function supplier(SettlementSupplierRequest $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            $registry = $this->suppliers->link($commissionSettlement, $request->registryId(), $request->user());

            return $this->ok(['supplier' => ['id' => $registry->id, 'name' => $registry->name]]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }

    /**
     * POST /api/commission-settlements/{commissionSettlement}/approve
     */
    public function approve(SettlementApproveRequest $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            return $this->respond($request->user(), $this->approval->approve($commissionSettlement, $request->choices(), $request->user()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }
}
