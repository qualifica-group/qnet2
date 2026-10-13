<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\PurchaseRequestLineStatusLabel;
use Illuminate\Http\Request;

/**
 * For-select projection of a line status label (spec 0248, D-4): `meta`
 * carries what the frontend needs to render the badge and key it by enum value.
 *
 * @mixin PurchaseRequestLineStatusLabel
 */
class PurchaseRequestLineStatusForSelectResource extends ForSelectResource
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
