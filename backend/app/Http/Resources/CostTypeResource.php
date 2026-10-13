<?php

namespace App\Http\Resources;

use App\Models\CostType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CostType
 */
class CostTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'nature' => $this->nature->value,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'abilities' => [
                'update' => (bool) $actor?->can('cost-types.update'),
                'delete' => (bool) $actor?->can('cost-types.delete'),
            ],
        ];
    }
}
