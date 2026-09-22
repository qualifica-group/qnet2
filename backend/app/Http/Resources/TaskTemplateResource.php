<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TaskTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskTemplate
 */
class TaskTemplateResource extends JsonResource
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
            'is_active' => $this->is_active,
            'stages' => TaskTemplateStageResource::collection($this->whenLoaded('stages')),
            'items_count' => $this->items->count(),
            'items' => TaskTemplateItemResource::collection($this->items),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
