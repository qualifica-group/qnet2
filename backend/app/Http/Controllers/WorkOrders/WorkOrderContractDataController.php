<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderContractData\UpdateWorkOrderLinePaymentRequest;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderContractDataBuilder;
use App\Services\WorkOrders\WorkOrderLinePaymentWriter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Read a commessa's contract data and save one line's payment data (spec
 * 0201). Gated on the OWNING commessa through WorkOrderPolicy::viewContractData
 * (GET) and managePayments (PATCH, in UpdateWorkOrderLinePaymentRequest).
 */
class WorkOrderContractDataController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WorkOrderContractDataBuilder $builder,
        private readonly WorkOrderLinePaymentWriter $writer,
    ) {}

    /**
     * GET /api/work-orders/{workOrder}/contract-data
     */
    public function show(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('viewContractData', $workOrder);

            return $this->ok($this->builder->build($workOrder, $this->actor($request)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * PATCH /api/work-orders/{workOrder}/contract-data/lines/{quoteLine}
     */
    public function updateLine(UpdateWorkOrderLinePaymentRequest $request, WorkOrder $workOrder, QuoteLine $quoteLine): JsonResponse
    {
        try {
            $this->writer->handle($workOrder, $quoteLine, $request->toData(), $this->actor($request));

            return $this->ok($this->builder->buildLine($workOrder, $quoteLine, $this->actor($request)), 'Payment data saved');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id, 'quoteLine' => $quoteLine->id]);
        }
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
