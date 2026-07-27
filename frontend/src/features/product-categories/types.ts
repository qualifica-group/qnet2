/**
 * Product Categories CRUD + tree types. The generic table types live in
 * `features/table/types.ts`; this file holds only what is genuinely
 * product-categories-specific. Source of truth: spec 0017 frozen
 * `data_contract`. Categories have no grid (they use a dedicated tree view),
 * so there is no `TableRow`-shaped row type here.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type {
  CustomFieldConfig,
  CustomFieldRelationTarget,
  CustomFieldType,
  CustomFieldValue,
} from '@/features/custom-fields/types'

/** A node of the category tree, as returned by `GET /product-categories/tree`. */
export interface ProductCategoryTreeNode {
  id: number
  name: string
  parent_id: number | null
  children: ProductCategoryTreeNode[]
  attributes_count: number
  products_count: number
  /** The node's OWN business function, null if it has none (spec 0023 AC-018). NOT the effective/inherited one. */
  business_function_id: number | null
}

/**
 * The two attribute-catalogue usage contexts (spec 0061): the same catalogue
 * attribute can be assigned to a category for Product, for Opportunity, or
 * both (two separate pivot rows). Default everywhere is `'opportunity'`
 * (backward compatible with every assignment that predates this spec).
 */
export type AttributeContext = 'product' | 'opportunity'

/** A category's own attribute assignment (pivot `attribute_category`). */
export interface ProductCategoryAttributeAssignment {
  attribute_id: number
  code: string
  name: string
  type: CustomFieldType
  is_required: boolean
  sort_order: number
  context: AttributeContext
}

/** An attribute inherited from an ancestor category (read-only in the form). */
export interface ProductCategoryInheritedAttribute {
  attribute_id: number
  code: string
  name: string
  type: CustomFieldType
  is_required: boolean
  context: AttributeContext
}

/** A category's own business function relation, hydrated (spec 0023). */
export interface ProductCategoryBusinessFunction {
  id: number
  name: string
}

/**
 * The business function effectively governing a category: its own, or the
 * nearest ancestor's when inherited (spec 0023). `source_category` is
 * populated only when `inherited` is true.
 */
export interface EffectiveBusinessFunction {
  id: number
  name: string
  inherited: boolean
  source_category: { id: number; name: string } | null
}

/**
 * Single category detail returned by GET/POST/PATCH /product-categories
 * (envelope `data`). Matches `ProductCategoryResource`.
 */
export interface ProductCategoryDetail {
  id: number
  name: string
  parent_id: number | null
  parent: { id: number; name: string } | null
  /** When false the category ignores its ancestry (barrier): no inherited attributes for it or its descendants. */
  inherits_attributes: boolean
  description: string | null
  attributes: ProductCategoryAttributeAssignment[]
  inherited_attributes: ProductCategoryInheritedAttribute[]
  created_at: string
  /** The category's OWN business function (null when absent or inherited — spec 0023). */
  business_function_id: number | null
  /** Hydrated projection of `business_function_id`. */
  business_function: ProductCategoryBusinessFunction | null
  /** Own or inherited business function; invariant: `inherited === true` implies `business_function_id === null`. */
  effective_business_function: EffectiveBusinessFunction | null
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `ProductCategoryDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /product-categories/{id}`.
 * Used to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface ProductCategoryDetailWithPermissions extends ProductCategoryDetail {
  permissions: ResourcePermissions
}

/**
 * A single attribute's effective assignment for a category, as returned by
 * `GET /product-categories/{id}/effective-attributes` — the product form's
 * dynamic-fields source (own assignments UNION every ancestor's).
 */
export interface EffectiveAttribute {
  id: number
  code: string
  name: string
  type: CustomFieldType
  description: string | null
  help_text: string | null
  placeholder: string | null
  icon: string | null
  config: CustomFieldConfig | null
  relation_target: CustomFieldRelationTarget | null
  is_required: boolean
  sort_order: number
  inherited: boolean
  context: AttributeContext
  options: {
    value: string
    label: string
    color: string | null
    icon: string | null
    sort_order: number
    is_default: boolean
  }[]
}

/**
 * Response of `GET /product-categories/{id}/attribute-layouts` (spec 0062
 * `data_contract`): the persisted layout (`null` = flat fallback) for the
 * requested (context, form_mode), plus the palette source — the SAME
 * effective-attributes catalogue `EffectiveAttribute` already models.
 */
export interface AttributeLayoutData {
  layout: LayoutBlob | null
  attributes: EffectiveAttribute[]
}

/** A single attribute-to-category assignment sent to the backend (full-replace sync per category). */
export interface AttributeAssignmentInput {
  attribute_id: number
  context: AttributeContext
  is_required?: boolean
  sort_order?: number
}

/** Payload for POST /product-categories (create). */
export interface CreateProductCategoryPayload {
  name: string
  parent_id?: number | null
  inherits_attributes?: boolean
  description?: string | null
  attributes?: AttributeAssignmentInput[]
  /** Own business function; omit or null when the category has none of its own (spec 0023). */
  business_function_id?: number | null
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/** Payload for PATCH /product-categories/{id} (partial update). */
export type UpdateProductCategoryPayload = Partial<CreateProductCategoryPayload>

/**
 * Payload for POST /product-categories/bulk-move (spec 0063): move every
 * selected category under one destination. `parent_id: null` moves them to
 * the root.
 */
export interface BulkMoveCategoriesPayload {
  category_ids: number[]
  parent_id: number | null
}

/** `moved` counts the categories actually reparented (one already sitting under the destination is a no-op). */
export interface BulkMoveCategoriesResult {
  moved: number
}

/** Why a bulk move was refused. The batch is all-or-nothing: nothing moved. */
export type BulkMoveConflictReason =
  | 'self_parent'
  | 'nested_selection'
  | 'cycle'
  | 'business_function_conflict'

/** One offending row of a refused bulk move. */
export interface BulkMoveConflict {
  id: number
  name: string
  detail: string
}

/** The `errors` block of a 422 bulk-move response. */
export interface BulkMoveConflictError {
  reason: BulkMoveConflictReason
  conflicts: BulkMoveConflict[]
}

/**
 * Discriminated form mode. Create optionally pre-selects a parent (the tree's
 * "add subcategory" action on a given node).
 */
export type ProductCategoryFormMode =
  | { type: 'create'; parentId: number | null }
  | { type: 'edit'; category: ProductCategoryDetailWithPermissions }
