<?php

namespace App\Http\Resources;

use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrderWorkflowStatus
 */
class WorkOrderWorkflowStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'group' => $this->group->value,
            'system_key' => $this->system_key,
            'progress_percentage' => $this->progress_percentage,
            'sort_order' => $this->sort_order,
        ];
    }
}
