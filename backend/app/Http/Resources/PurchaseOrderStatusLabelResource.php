<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseOrderStatusLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderStatusLabel
 */
class PurchaseOrderStatusLabelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'system_key' => $this->system_key,
            'name' => $this->name,
            'color' => $this->color,
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
        ];
    }
}
