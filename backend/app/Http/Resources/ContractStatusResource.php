<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ContractStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContractStatus
 */
class ContractStatusResource extends JsonResource
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
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            // spec 0072: the four mandatory system rows (D-2) and the fixed
            // classification (`group`, App\Enums\ContractStatusGroup).
            'system_key' => $this->system_key,
            'group' => $this->group->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
