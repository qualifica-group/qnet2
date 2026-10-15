<?php

namespace App\Http\Controllers\CommissionSettlements;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CommissionSettlements\SettlementDocumentRequest;
use App\Models\CommissionSettlement;
use App\Services\Commissions\Settlements\SettlementDocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Agent document of a settlement (spec 0267): register and remove. Thin
 * controller; the state rules (409) and the tolerance (422) are the Service's.
 *
 * @see SettlementDocumentService
 */
class SettlementDocumentController extends BaseApiController
{
    use AuthorizesRequests, RespondsWithSettlement;

    public function __construct(private readonly SettlementDocumentService $service) {}

    /**
     * POST /api/commission-settlements/{commissionSettlement}/document
     */
    public function store(SettlementDocumentRequest $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            return $this->respond($request->user(), $this->service->register($commissionSettlement, $request->document(), $request->user()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }

    /**
     * DELETE /api/commission-settlements/{commissionSettlement}/document
     */
    public function destroy(Request $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        try {
            $this->authorize('receiveDocument', $commissionSettlement);

            return $this->respond($request->user(), $this->service->remove($commissionSettlement));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlement' => $commissionSettlement->id]);
        }
    }
}
