<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UserCategoryTabPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The actor's category tab strip preferences (spec 0184). The favourite ids
 * are already reduced to existing categories by the controller.
 *
 * @mixin UserCategoryTabPreference
 */
class CategoryTabPreferencesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'favorite_category_ids' => array_values(array_map('intval', $this->favorite_category_ids)),
            'show_only_favorites' => (bool) $this->show_only_favorites,
        ];
    }
}
