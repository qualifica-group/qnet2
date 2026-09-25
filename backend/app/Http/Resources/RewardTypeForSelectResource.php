<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\RewardType;
use Illuminate\Http\Request;

/**
 * For-select projection of a RewardType (GET /api/reward-types/for-select,
 * spec 0058 D-7).
 *
 * Minimal by design (ADR 0011): label = name, plus `meta.color` — the palette
 * token the "abbinamento buono" chip renders. Carrying it here lets that
 * control draw a picked chip without `GET /api/reward-types/{id}`, which is
 * gated on `reward-types.view`: an operator of Gestione Richieste may assign
 * a voucher without holding the catalogue module.
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
            'meta' => ['color' => $this->color],
        ];
    }
}
