<?php

namespace App\Models;

use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use App\Support\ManagerPositions;
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
#[Fillable(['name', 'parent_id', 'inherits_product_attributes', 'inherits_quote_attributes', 'description', 'business_function_id', 'requires_quote', 'is_selectable', 'management_mode', 'single_quote_per_opportunity', 'manager_labels', 'inherits_manager_labels'])]
class ProductCategory extends BaseModel
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * Highest valid "Gestore Account" pivot position (spec 0080 amendment
     * A1): shared with the validation-layer cap on the number of managers a
     * record may have (App\Http\Requests\Concerns\ValidatesManagerSlots::
     * MAX_MANAGERS) via App\Support\ManagerPositions, the single source of
     * truth. `manager_labels` keys outside 1..this are rejected.
     */
    public const int MANAGER_LABEL_MAX_POSITION = ManagerPositions::MAX;

    /** Max length of a single manager label (spec 0080). */
    public const int MANAGER_LABEL_MAX_LENGTH = 60;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inherits_product_attributes' => 'boolean',
            // Spec 0084 — the second usage context's own inheritance barrier
            // (App\Enums\AttributeContext::Quote), independent of the one
            // above: a category may inherit its ancestors' Offerta attributes
            // while cutting itself off from the Prodotto ones.
            'inherits_quote_attributes' => 'boolean',
            // Owned by the branch ROOT and mirrored on every descendant by
            // RequiresQuoteInheritance — a child's own column is never
            // authored directly, it only ever reflects its root's.
            'requires_quote' => 'boolean',
            // Spec 0074 — owned by THIS node and never inherited (unlike
            // requires_quote): a container category can be unselectable while
            // its children stay selectable, which is the whole point.
            'is_selectable' => 'boolean',
            // Spec 0077 — owned by the branch ROOT and mirrored on every
            // descendant by CategoryManagementModeInheritance, same shape as
            // requires_quote: a child's own column is never authored
            // directly, it only ever reflects its root's.
            'management_mode' => CategoryManagementMode::class,
            // User directive 2026-08-07 — owned by the branch ROOT and
            // mirrored on every descendant by
            // SingleQuotePerOpportunityInheritance, same shape as
            // management_mode: when true an opportunity covered by this
            // branch carries at most one quote.
            'single_quote_per_opportunity' => 'boolean',
            // Spec 0080 — sparse position("1".."4")->label map, own
            // assignments only; null/[] = no own labels. Read-side resolution
            // (own UNION inherited) lives in CategoryManagerLabelResolver,
            // never here.
            'manager_labels' => 'array',
            // Spec 0080 — per-context inheritance barrier for manager_labels,
            // same shape as inherits_product_attributes/
            // inherits_quote_attributes (spec 0061).
            'inherits_manager_labels' => 'boolean',
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
     * category can keep inheriting Offerta attributes while cutting itself
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
