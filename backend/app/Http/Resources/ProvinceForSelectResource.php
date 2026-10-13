<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Province;
use Illuminate\Http\Request;

/**
 * For-select projection of a Province (GET /api/provinces/for-select).
 *
 * label = name, subtitle = the parent region's name (omitted when the relation
 * has no match). Relies on ProvinceForSelectController eager-loading
 * `state:id,name` — never a second query per row.
 *
 * @mixin Province
 */
class ProvinceForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->localizedName(),
            'subtitle' => $this->state?->localizedName(),
        ];
    }
}
