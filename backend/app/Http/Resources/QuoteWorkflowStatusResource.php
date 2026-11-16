<?php

namespace App\Http\Resources;

use App\Models\QuoteWorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuoteWorkflowStatus
 */
class QuoteWorkflowStatusResource extends JsonResource
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
            'sort_order' => $this->sort_order,
            'system_key' => $this->system_key,
            'group' => $this->group->value,
            // Spec 0404 part A: the linked system status; `group` is its read-only copy.
            'quote_system_status_id' => $this->quote_system_status_id,
            'quote_system_status' => QuoteSystemStatusSummary::of($this->systemStatus),
            'requires_note' => $this->requires_note,
        ];
    }
}
