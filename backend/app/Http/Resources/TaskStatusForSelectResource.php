<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\TaskStatus;
use Illuminate\Http\Request;

/**
 * For-select projection of a TaskStatus (GET /api/task-statuses/for-select, spec
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
 * `meta.system_key` (D-5) lets a select recognize/pin the three protected
 * rows, `meta.group` carries the phase (so a select can group or filter by
 * it without a second lookup), and `meta.completion_percentage` (D-6) lets
 * the Task form show the DERIVED percentage update as another status is
 * picked, with no extra request (AC-084).
 *
 * @mixin TaskStatus
 */
class TaskStatusForSelectResource extends ForSelectResource
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
                'system_key' => $this->system_key,
                'group' => $this->group->value,
                'completion_percentage' => $this->completion_percentage,
            ],
        ];
    }
}
