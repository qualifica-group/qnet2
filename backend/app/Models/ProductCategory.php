<?php

namespace App\Models;

use App\Enums\AttributeContext;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product category tree node (spec 0017): unlimited-depth parent/child
 * hierarchy. A category's EFFECTIVE attributes are its own `attributes()`
 * assignments UNION every ancestor's (see ProductCategoryService).
 */
#[Fillable(['name', 'parent_id', 'inherits_product_attributes', 'inherits_opportunity_attributes', 'description', 'business_function_id'])]
class ProductCategory extends BaseModel
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inherits_product_attributes' => 'boolean',
            'inherits_opportunity_attributes' => 'boolean',
            // Spec 0013 — external data migration: the source system's id for a
            // migrated category, guarded (not in #[Fillable]) so it is only ever
            // set by property assignment post-create. Also the remap key for the
            // self-referential `parent_id` (child → parent via old_id).
            'old_id' => 'integer',
        ];
    }

    /**
     * Whether this category pulls its ancestors' attributes IN $context. The
     * two usage contexts (spec 0061) each carry their OWN barrier flag, so a
     * category can keep inheriting Opportunity attributes while cutting itself
     * off from the Product ones — the barriers are fully independent.
     */
    public function inheritsAttributesIn(AttributeContext $context): bool
    {
        return (bool) $this->getAttribute($context->inheritanceColumn());
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * The category's OWN business function assignment (spec 0023) — never
     * the EFFECTIVE (own-or-inherited) one, which is resolved read-side by
     * CategoryHierarchy::effectiveBusinessFunction().
     */
    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    /**
     * Attributes assigned directly to THIS category (own assignments — not
     * including attributes only inherited from an ancestor).
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_category', 'category_id', 'attribute_id')
            ->withPivot(['is_required', 'sort_order', 'context'])
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * The opportunities against this product category, via at least one
     * `opportunity_product_lines` row (spec 0040 amendment rev.3, BR-3:
     * restrict-on-delete — ProductCategoryService::delete() guards on this
     * before deleting). A BelongsToMany rather than a direct HasMany since
     * the FK now lives on the pivot row, not on `opportunities` itself.
     *
     * @return BelongsToMany<Opportunity, $this>
     */
    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_product_lines');
    }
}
