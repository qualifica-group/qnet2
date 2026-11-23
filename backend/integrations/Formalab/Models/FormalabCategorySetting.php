<?php

declare(strict_types=1);

namespace Integrations\Formalab\Models;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Formalab settings of one product category (spec 0423): `enabled` is
 * only meaningful on a root category (its descendants inherit it), while
 * `field_map` (list of {formalab_field, attribute_code}) is inherited from
 * the nearest ancestor declaring one; null = not declared here.
 */
#[Fillable(['product_category_id', 'enabled', 'field_map', 'updated_by'])]
class FormalabCategorySetting extends Model
{
    protected $table = 'formalab_category_settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_category_id' => 'integer',
            'enabled' => 'boolean',
            'field_map' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
