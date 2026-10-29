<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * For-select projection of a work order workflow status (spec 0323). Not the
 * ForSelectResource `{id, label}` shape: the picker groups by workflow and
 * shows the percentage, so it needs the full row. `work_order_workflow_name`
 * comes from the join in WorkOrderWorkflowStatusForSelectService (null for
 * the default set).
 *
 * @mixin WorkOrderWorkflowStatus
 */
class WorkOrderWorkflowStatusForSelectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'progress_percentage' => $this->progress_percentage,
            'system_key' => $this->system_key,
            'work_order_workflow_id' => $this->work_order_workflow_id,
            'work_order_workflow_name' => $this->getAttribute('work_order_workflow_name'),
        ];
    }
}
