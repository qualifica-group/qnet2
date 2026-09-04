<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskStatus
 */
class TaskStatusResource extends JsonResource
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
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            // spec 0101, D-5/D-6: the six mandatory system rows and the
            // completion the Task PROJECTS from its status.
            'system_key' => $this->system_key,
            'completion_percentage' => $this->completion_percentage,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
