<?php

namespace App\Http\Resources;

use App\Models\ProductTypology;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductTypology
 */
class ProductTypologyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'supplier_commission_enabled' => $this->supplier_commission_enabled,
            'supplier_commission_direction' => $this->supplier_commission_direction?->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
