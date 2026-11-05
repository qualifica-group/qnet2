<?php

namespace App\Http\Resources;

use App\Models\TreasuryType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TreasuryType
 */
class TreasuryTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id,
            'chart_account_id' => $this->chart_account_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'abilities' => [
                'update' => (bool) $actor?->can('treasury-types.update'),
                'delete' => (bool) $actor?->can('treasury-types.delete'),
            ],
        ];
    }
}
