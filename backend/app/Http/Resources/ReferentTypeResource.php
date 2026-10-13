<?php

namespace App\Http\Resources;

use App\Authorization\LegacyIdVisibility;
use App\Models\ReferentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferentType
 */
class ReferentTypeResource extends JsonResource
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
            'created_at' => $this->created_at,
        ];
    }
}
