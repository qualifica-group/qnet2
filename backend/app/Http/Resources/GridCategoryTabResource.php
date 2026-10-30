<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category tab of the Opportunities/Offerte grids (spec 0335): id/name plus
 * the DISTINCT-row count annotated by GridCategoryTabsResolver.
 *
 * @mixin ProductCategory
 */
class GridCategoryTabResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'records_count' => (int) $this->records_count,
        ];
    }
}
