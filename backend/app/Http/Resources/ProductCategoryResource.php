<?php

namespace App\Http\Resources;

use App\Authorization\LegacyIdVisibility;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ChartOfAccounts\EffectiveRevenueType;
use App\Services\ChartOfAccounts\RevenueTypeRef;
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
            'old_id' => $this->when(LegacyIdVisibility::allows($request->user()), $this->old_id),
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'parent' => $this->parent !== null ? ['id' => $this->parent->id, 'name' => $this->parent->name] : null,
            // Spec 0061 follow-up (spec 0084 replaces the Opportunity context
            // with the Offerta one; spec 0098 adds the Commessa one): one
            // inheritance barrier per usage context, decoupled — the Product
            // section can ignore its ancestry while the Offerta/Commessa ones
            // keep inheriting, and vice versa.
            'inherits_product_attributes' => (bool) $this->inherits_product_attributes,
            'inherits_quote_attributes' => (bool) $this->inherits_quote_attributes,
            'inherits_work_order_attributes' => (bool) $this->inherits_work_order_attributes,
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
            // Spec 0208: this node's OWN flag; the effective value (an
            // inactive ancestor deactivates the subtree) is CategoryActivity's.
            'is_active' => (bool) $this->is_active,
            // Spec 0131 (user directive 2026-09-18): this node's OWN override
            // of the report flag, null = inherited. The effective value and
            // its source are attached by the controller.
            'is_reportable' => $this->is_reportable,
            // Spec 0141: this node's OWN report column selection, null =
            // inherited. The effective value and its source are attached by
            // the controller, mirroring is_reportable/effective_is_reportable.
            'report_columns' => $this->report_columns,
            // Spec 0077: the EFFECTIVE card-line policy — on a child this
            // already mirrors its root (CategoryManagementModeInheritance
            // keeps the column in sync), so no walk is needed here.
            // `management_mode_source_category` is attached by the
            // controller alongside `requires_quote_source_category`.
            'management_mode' => $this->management_mode->value,
            // User directive 2026-08-07: the EFFECTIVE single-quote rule —
            // already mirrored from the root on every descendant, so no walk
            // is needed here. `single_quote_per_opportunity_source_category`
            // is attached by the controller alongside the other two.
            'single_quote_per_opportunity' => (bool) $this->single_quote_per_opportunity,
            // Spec 0091: the EFFECTIVE contract rule — already mirrored from
            // the root on every descendant, so no walk is needed here either.
            // `generates_contract_source_category` is attached by the
            // controller alongside the other three.
            'generates_contract' => (bool) $this->generates_contract,
            // Spec 0267: the EFFECTIVE multi-quote work-order rule, mirrored
            // from the root like the flags above.
            'multi_quote_work_order' => (bool) $this->multi_quote_work_order,
            // Spec 0389: the EFFECTIVE usage area, mirrored from the root.
            'usage_area' => $this->usage_area->value,
            // User request 2026-11-11: the EFFECTIVE "several open
            // opportunities" rule, mirrored from the root.
            'multiple_open_opportunities' => (bool) $this->multiple_open_opportunities,
            // Spec 0114: the EFFECTIVE simplified-offer-line rule — already
            // mirrored from the root on every descendant, so no walk is
            // needed here either. `simplified_offer_line_source_category` is
            // attached by the controller alongside the other four.
            'simplified_offer_line' => (bool) $this->simplified_offer_line,
            // Spec 0188: the node's OWN declaration (null = inherits).
            'simplified_offer_line_override' => $this->simplified_offer_line_override,
            'business_function_id' => $this->business_function_id,
            'business_function' => $this->businessFunction !== null
                ? ['id' => $this->businessFunction->id, 'name' => $this->businessFunction->name]
                : null,
            // Spec 0350: own default revenue sub-account and the effective one (own or from the closest ancestor).
            'revenue_type_id' => $this->revenue_type_id,
            'revenue_type' => RevenueTypeRef::find($this->revenue_type_id),
            // Spec 0374: legacy root and the current root it continues.
            'is_legacy' => (bool) $this->is_legacy,
            'current_category_id' => $this->current_category_id,
            'current_category' => $this->currentCategory !== null
                ? ['id' => $this->currentCategory->id, 'name' => $this->currentCategory->name]
                : null,
            'effective_revenue_type' => app(EffectiveRevenueType::class)->forCategory($this->resource),
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
