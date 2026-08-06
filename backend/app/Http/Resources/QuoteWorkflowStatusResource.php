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
            'requires_note' => $this->requires_note,
        ];
    }
}
