<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

/**
 * For-select projection of a ProductCategory (GET /api/product-categories/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. `meta`
 * (spec 0040 BR-4) carries the category's EFFECTIVE (own-or-inherited)
 * business function, batch-resolved by ProductCategoryService::forSelect and
 * stashed on the model as `business_function_summary` — always present
 * (null when neither the category nor any ancestor has one). Spec 0077 adds
 * `root_category_id`/`management_mode` (the branch root id and its
 * EFFECTIVE, inherited card-line policy), stashed as `root_management_mode`
 * by the same batched call — lets the form know a card's policy right after
 * the first row is picked, with no extra request.
 *
 * @mixin ProductCategory
 */
class ProductCategoryForSelectResource extends ForSelectResource
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
                'business_function' => $this->business_function_summary,
                'root_category_id' => $this->root_management_mode['root_id'] ?? null,
                'management_mode' => $this->root_management_mode['management_mode']?->value ?? null,
            ],
        ];
    }
}
