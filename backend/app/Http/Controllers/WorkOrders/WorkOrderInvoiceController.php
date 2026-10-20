<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderInvoiceOverviewBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Read-only list of the active and passive proforma/invoices of a commessa
 * (spec 0282). Gated on the OWNING commessa through
 * WorkOrderPolicy::viewInvoices (permission + membership scoping).
 */
class WorkOrderInvoiceController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly WorkOrderInvoiceOverviewBuilder $overview) {}

    /**
     * GET /api/work-orders/{workOrder}/invoices
     */
    public function show(WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('viewInvoices', $workOrder);

            return $this->ok($this->overview->build($workOrder));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }
}
