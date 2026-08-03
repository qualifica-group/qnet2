<?php

namespace App\Http\Resources;

use App\Models\RewardStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RewardStatus
 */
class RewardStatusResource extends JsonResource
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
            // spec 0073: the phase classification driving the lifecycle
            // automation (App\Enums\RewardStatusGroup).
            'group' => $this->group->value,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            // spec 0060: the mandatory system row (D-2).
            'system_key' => $this->system_key,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
