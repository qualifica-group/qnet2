<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Http\Requests\WorkOrders\UpdateWorkOrderRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Table\FormRequestCellValidator;
use App\Services\WorkOrderService;
use App\Support\ManagerPositions;

/**
 * The write behind the work-orders grid's inline cell edit (spec 0206, D-2):
 * the cell's one field goes through UpdateWorkOrderRequest — the form's own
 * rules and field-permission gate — and then WorkOrderService::update(), the
 * same pair the detail's PATCH /api/work-orders/{workOrder} runs.
 */
final class WorkOrderCellWriter
{
    private const string ROUTE_PARAMETER = 'workOrder';

    private const string PARTICIPANT_SLOTS_FIELD = 'participant_slots';

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
            [$fieldKey => $fieldKey === self::PARTICIPANT_SLOTS_FIELD ? $this->participantSlots($workOrder, $value) : $value],
        );

        return $this->service->update($workOrder, $request->toData());
    }

    /**
     * The cell holds a list of people, the form a positional team (spec 0206, D-3).
     *
     * @param  array<int, int|string>  $userIds
     * @return array<int, int|null>
     */
    private function participantSlots(WorkOrder $workOrder, array $userIds): array
    {
        $workOrder->loadMissing('participants');

        return ManagerPositions::slotsFromIds(ManagerPositions::positionsOf($workOrder->participants), array_map('intval', $userIds));
    }
}
