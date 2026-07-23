<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\RewardType;
use Illuminate\Http\Request;

/**
 * For-select projection of a RewardType (GET /api/reward-types/for-select,
 * spec 0058 D-7).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar/meta — there
 * is no system_key/group to surface, unlike its template
 * OpportunityStatusForSelectResource.
 *
 * @mixin RewardType
 */
class RewardTypeForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
        ];
    }
}
