<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Sector;
use Illuminate\Http\Request;

/**
 * For-select projection of a Sector (GET /api/sectors/for-select,
 * spec 0020).
 *
 * Minimal by design (ADR 0011): label = Sector::selectLabel() ("{code} —
 * {name}", spec 0213), no subtitle/avatar.
 *
 * @mixin Sector
 */
class SectorForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->selectLabel(),
        ];
    }
}
