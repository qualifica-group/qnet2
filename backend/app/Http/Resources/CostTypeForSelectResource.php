<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\CostType;
use Illuminate\Http\Request;

/**
 * For-select projection of a CostType (GET /api/cost-types/for-select, spec 0210).
 *
 * @mixin CostType
 */
class CostTypeForSelectResource extends ForSelectResource
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
