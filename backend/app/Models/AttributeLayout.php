<?php

namespace App\Models;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AttributeLayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A category's configured visual layout for one (context, scope) pair (spec
 * 0062, D1/D3 revised — the `form_mode` column holds an
 * App\Enums\LayoutFormScope: the shared `all` layout or a per-mode override
 * of it): sections -> rows -> items, always allow-list validated
 * against the category's effective attributes before persisting
 * (App\Services\ProductCategories\AttributeLayoutValidator). Absence of a row
 * for a given (product_category_id, context, form_mode) means "flat
 * rendering", the pre-existing behavior (AC-007) — this table is purely
 * additive/opt-in.
 */
#[Fillable(['product_category_id', 'context', 'form_mode', 'layout'])]
class AttributeLayout extends BaseModel
{
    /** @use HasFactory<AttributeLayoutFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'context' => AttributeContext::class,
            'form_mode' => LayoutFormScope::class,
        ];
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
