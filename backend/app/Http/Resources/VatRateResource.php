<?php

namespace App\Http\Resources;

use App\Authorization\LegacyIdVisibility;
use App\Models\VatRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VatRate
 */
class VatRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_id' => $this->when(LegacyIdVisibility::allows($request->user()), $this->old_id),
            'name' => $this->name,
            'rate' => $this->rate,
            'created_at' => $this->created_at,
        ];
    }
}
