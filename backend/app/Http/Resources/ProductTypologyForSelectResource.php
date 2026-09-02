<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\ProductTypology;
use Illuminate\Http\Request;

/**
 * For-select projection of a ProductTypology (GET
 * /api/product-typologies/for-select, spec 0099).
 *
 * `label` = name. `meta.code` rides along so a consumer can key on the
 * STABLE code rather than the renameable name (D-2) — it is what the offer
 * summary's zero-filled list uses as a React key, never as a business
 * condition (requirement 7: no hardcoded typology anywhere).
 *
 * @mixin ProductTypology
 */
class ProductTypologyForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => null,
            'meta' => ['code' => $this->code],
        ];
    }
}
