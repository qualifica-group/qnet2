<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Migrations\Realign\AbstractSourceRealignTarget;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderStage;
use Illuminate\Database\Eloquent\Model;

/** Stato di lavorazione della commessa (spec 0372 D-13): runs after the commessa. */
final class WorkOrderWorkflowStatusesTarget extends AbstractSourceRealignTarget
{
    public function source(): string
    {
        return 'work-order-workflow-statuses';
    }

    protected function modelClass(): string
    {
        return WorkOrder::class;
    }

    public function touchSubjects(Model $record): array
    {
        return [
            [WorkOrder::class, $record->getKey()],
            [WorkOrderStage::class, WorkOrderStage::query()->where('work_order_id', $record->getKey())->select('id')],
            [WorkOrderLinePayment::class, WorkOrderLinePayment::query()->where('work_order_id', $record->getKey())->select('id')],
        ];
    }

    public function snapshot(Model $record): array
    {
        /** @var WorkOrder $record */
        return ['status' => $record->workOrderWorkflowStatus?->name];
    }
}
