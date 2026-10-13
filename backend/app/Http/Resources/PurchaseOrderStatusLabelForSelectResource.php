<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\PurchaseOrderStatusLabel;
use Illuminate\Http\Request;

/**
 * For-select projection of a PurchaseOrderStatusLabel (spec 0248, D-4): label =
 * name, `meta` carries the enum value (`system_key`, what documents expose as
 * `status`) and the presentation attributes. Presentation only, never a
 * business condition.
 *
 * @mixin PurchaseOrderStatusLabel
 */
class PurchaseOrderStatusLabelForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'meta' => [
                'system_key' => $this->system_key,
                'color' => $this->color,
                'icon' => $this->icon,
                'sort_order' => $this->sort_order,
            ],
        ];
    }
}
