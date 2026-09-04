<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\TaskType;
use Illuminate\Http\Request;

/**
 * For-select projection of a TaskType (GET /api/task-types/for-select, spec
 * 0101).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. `meta`
 * carries the badge attributes (`color`, `icon`) so an entity-backed select
 * renders the SAME badge the grid does, without a second lookup —
 * presentation only, never a business condition. `is_active` rides along
 * for the reorder sheet, which asks for `include_inactive` and would
 * otherwise show deactivated rows indistinguishable from the rest; it
 * survives ForSelectResource::toArray()'s array_filter, which only strips
 * null OPTIONAL keys at the top level and never descends into `meta`.
 *
 * @mixin TaskType
 */
class TaskTypeForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'meta' => [
                'color' => $this->color,
                'icon' => $this->icon,
                'is_active' => $this->is_active,
            ],
        ];
    }
}
