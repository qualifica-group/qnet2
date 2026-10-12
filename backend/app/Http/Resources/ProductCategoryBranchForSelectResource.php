<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

/**
 * For-select projection of a product-category BRANCH
 * (GET /api/product-category-branches/for-select, spec 0092), also used by
 * the node picker (GET /api/product-category-nodes/for-select, spec 0223).
 *
 * Strictly `{id, label}`: unlike ProductCategoryForSelectResource this feeds
 * ONE consumer, the quote-workflow criteria editor, which renders a plain
 * label — none of the sibling's `meta` (effective business function, branch
 * root, management mode) has a reader here, and emitting it would cost two
 * batched hierarchy resolutions per page for nothing.
 *
 * @mixin ProductCategory
 */
class ProductCategoryBranchForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
        ];
    }
}
