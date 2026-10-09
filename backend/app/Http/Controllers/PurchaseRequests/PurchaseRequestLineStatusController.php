<?php

namespace App\Http\Controllers\PurchaseRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseRequests\PurchaseRequestLineStatusRequest;
use App\Http\Resources\PurchaseRequestLineStatusLogResource;
use App\Models\PurchaseRequestLine;
use App\Services\PurchaseRequests\PurchaseRequestLineStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Status changes of purchase request lines and their history (spec 0208, D-8,
 * D-9). The change endpoint has no Policy ability of its own: the actor's
 * capabilities per request (function manager, fulfill, manageStatuses) are
 * resolved by PurchaseRequestLineStatusService, which refuses the whole change
 * with a 403 when any line is out of reach.
 *
 * @see PurchaseRequestLineStatusService
 */
class PurchaseRequestLineStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly PurchaseRequestLineStatusService $service) {}

    /**
     * POST /api/purchase-request-lines/status
     */
    public function change(PurchaseRequestLineStatusRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->service->change(
                $request->user(),
                $request->lineIds(),
                $request->toStatus(),
                $request->reason(),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/purchase-request-lines/{purchaseRequestLine}/status-logs (most recent first)
     */
    public function logs(PurchaseRequestLine $purchaseRequestLine): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseRequestLine->purchaseRequest);

            return $this->ok(PurchaseRequestLineStatusLogResource::collection(
                $purchaseRequestLine->statusLogs()->with('user:id,name')->get(),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['line' => $purchaseRequestLine->id]);
        }
    }
}
