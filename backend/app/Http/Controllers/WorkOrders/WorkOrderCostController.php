<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderCosts\SyncWorkOrderCostsRequest;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderCostOverviewBuilder;
use App\Services\WorkOrders\WorkOrderCostWriter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Read and replace a commessa's actual costs (spec 0190). Gated on the
 * OWNING commessa through WorkOrderPolicy::viewCosts/manageCosts (permission
 * + membership scoping); the PUT's own authorization sits in
 * SyncWorkOrderCostsRequest::authorize().
 */
class WorkOrderCostController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WorkOrderCostWriter $writer,
        private readonly WorkOrderCostOverviewBuilder $overview,
    ) {}

    /**
     * GET /api/work-orders/{workOrder}/costs
     */
    public function show(WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('viewCosts', $workOrder);

            return $this->ok($this->overview->build($workOrder));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * PUT /api/work-orders/{workOrder}/costs
     */
    public function sync(SyncWorkOrderCostsRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->writer->handle($workOrder, $request->lines());

            return $this->ok($this->overview->build($workOrder->refresh()), 'Costs saved');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }
}
