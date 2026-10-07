<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Http\Requests\WorkOrders\UpdateWorkOrderRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Table\FormRequestCellValidator;
use App\Services\WorkOrderService;

/**
 * The write behind the work-orders grid's inline cell edit (spec 0206, D-2):
 * the cell's one field goes through UpdateWorkOrderRequest — the form's own
 * rules and field-permission gate — and then WorkOrderService::update(), the
 * same pair the detail's PATCH /api/work-orders/{workOrder} runs.
 */
final class WorkOrderCellWriter
{
    private const string ROUTE_PARAMETER = 'workOrder';

    public function __construct(
        private readonly FormRequestCellValidator $formRequest,
        private readonly WorkOrderService $service,
    ) {}

    public function write(WorkOrder $workOrder, string $fieldKey, mixed $value, User $actor): WorkOrder
    {
        $request = $this->formRequest->validate(
            UpdateWorkOrderRequest::class,
            self::ROUTE_PARAMETER,
            $workOrder,
            $actor,
            [$fieldKey => $value],
        );

        return $this->service->update($workOrder, $request->toData());
    }
}
