<?php

namespace App\Http\Resources;

use App\Models\Attribute;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\PreserveKeys;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductCategory
 *
 * #[PreserveKeys]: `manager_labels` (spec 0080) is a sparse position("1".."4")
 * ->label map — JsonResource's default filter() reindexes any NESTED array
 * whose keys are ALL numeric (Illuminate\Http\Resources\ConditionallyLoads
 * Attributes::removeMissingValues()), which would silently turn
 * `{"2":"Operatore"}` into `["Operatore"]` on the wire. Every other array
 * field here is already 0-indexed-sequential, so this is a no-op for them.
 */
#[PreserveKeys]
class ProductCategoryResource extends JsonResource
{
    /**
     * Own attribute assignments only — inherited ones are attached via
     * `additional(['inherited_attributes' => ...])` by the controller
     * (ProductCategoryService::inheritedAttributes), never merged here.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'parent' => $this->parent !== null ? ['id' => $this->parent->id, 'name' => $this->parent->name] : null,
            // Spec 0061 follow-up (spec 0084 adds the third context): one
            // inheritance barrier per usage context, decoupled — the Product
            // section can ignore its ancestry while the Opportunity or
            // Offerta ones keep inheriting, and vice versa.
            'inherits_product_attributes' => (bool) $this->inherits_product_attributes,
            'inherits_opportunity_attributes' => (bool) $this->inherits_opportunity_attributes,
            // Spec 0084: the third usage context's own inheritance barrier.
            'inherits_quote_attributes' => (bool) $this->inherits_quote_attributes,
            'description' => $this->description,
            // The EFFECTIVE flag: on a child this already mirrors its root
            // (RequiresQuoteInheritance keeps the column in sync), so no walk
            // is needed here. `requires_quote_source_category` — which root it
            // comes from — is attached by the controller alongside
            // `effective_business_function`.
            'requires_quote' => (bool) $this->requires_quote,
            // Spec 0074: whether this node may be picked as a classification
            // target. Per-node, never inherited — a false one still parents
            // selectable children.
            'is_selectable' => (bool) $this->is_selectable,
            // Spec 0077: the EFFECTIVE card-line policy — on a child this
            // already mirrors its root (CategoryManagementModeInheritance
            // keeps the column in sync), so no walk is needed here.
            // `management_mode_source_category` is attached by the
            // controller alongside `requires_quote_source_category`.
            'management_mode' => $this->management_mode->value,
            'business_function_id' => $this->business_function_id,
            'business_function' => $this->businessFunction !== null
                ? ['id' => $this->businessFunction->id, 'name' => $this->businessFunction->name]
                : null,
            'attributes' => $this->attributes->map(fn (Attribute $attribute): array => [
                'attribute_id' => $attribute->id,
                'code' => $attribute->code,
                'name' => $attribute->name,
                'type' => $attribute->type,
                'is_required' => (bool) $attribute->pivot->is_required,
                'sort_order' => (int) $attribute->pivot->sort_order,
                // Spec 0061: which section ("Attributi Prodotto" vs
                // "Attributi Opportunita'") this OWN assignment belongs to —
                // the frontend splits this single flat list by the tag.
                'context' => (string) $attribute->pivot->context,
            ])->all(),
            // Spec 0080: own "Gestore Account" label overrides only — the
            // ANCESTORS' resolved ones are attached by the controller as the
            // sibling `inherited_manager_labels` key, never merged here (same
            // treatment as `attributes`/`inherited_attributes`).
            'manager_labels' => $this->manager_labels ?? [],
            'inherits_manager_labels' => (bool) $this->inherits_manager_labels,
            'created_at' => $this->created_at,
        ];
    }
}
