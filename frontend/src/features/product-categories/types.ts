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
  /** The EFFECTIVE quote flag: authored by the branch root, mirrored on every descendant server-side. */
  requires_quote: boolean
  /** Whether the node may be picked as a classification target (spec 0074). Per-node: a false one still parents selectable children. */
  is_selectable: boolean
  /** The EFFECTIVE management mode: authored by the branch root, mirrored on every descendant server-side (spec 0077). */
  management_mode: CategoryManagementMode
  /** The EFFECTIVE single-offer rule: authored by the branch root, mirrored on every descendant server-side. */
  single_quote_per_opportunity: boolean
}

/**
 * How Category Product lines behave on a card ("single" = one line only,
 * "multiple" = several distinct lines): authored on the ROOT category and
 * inherited by every descendant (spec 0077 D-2). Same inheritance shape as
 * `requires_quote` — see `management-mode-inheritance.ts`.
 */
export type CategoryManagementMode = 'single' | 'multiple'

/**
 * The two attribute-catalogue usage contexts (spec 0061): the same catalogue
 * attribute can be assigned to a category for Product, for Offerta, or both
 * (two separate pivot rows). There is no default — every caller names its
 * context, and the endpoints 422 without one.
 *
 * Spec 0084 retired the `'opportunity'` context: its dynamic section moved to
 * the Offerta, leaving the category-side configuration unread. Do not add it
 * back.
 */
export type AttributeContext = 'product' | 'quote'

/**
 * A category's manager-label overrides (spec 0080): position ("1".."4",
 * matching `ProductCategory::MANAGER_LABEL_MAX_POSITION`) to the custom
 * denomination for that G.A. level. Only ever carries valorized positions —
 * an empty/whitespace label is never a key.
 */
export type ManagerLabels = Record<string, string>

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
  /** When false the category ignores its ancestry for PRODUCT attributes (barrier): none inherited by it or its descendants. */
  inherits_product_attributes: boolean
  /** Spec 0084: same barrier for OFFERTA attributes — fully independent of the product one. */
  inherits_quote_attributes: boolean
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
  /** Whether the category is quoted — authored by the branch ROOT, mirrored here on every descendant. */
  requires_quote: boolean
  /** The root `requires_quote` is inherited from; null when this category IS the root and owns the flag. */
  requires_quote_source_category: { id: number; name: string } | null
  /** Whether the category may be picked as a classification target (spec 0074). */
  is_selectable: boolean
  /** How Category Product lines behave on a card — authored by the branch ROOT, mirrored here on every descendant (spec 0077). */
  management_mode: CategoryManagementMode
  /** The root `management_mode` is inherited from; null when this category IS the root and owns the value. */
  management_mode_source_category: { id: number; name: string } | null
  /** Whether an opportunity covered by this category accepts a single offer — authored by the branch ROOT, mirrored here on every descendant. */
  single_quote_per_opportunity: boolean
  /** The root `single_quote_per_opportunity` is inherited from; null when this category IS the root and owns the flag. */
  single_quote_per_opportunity_source_category: { id: number; name: string } | null
  /** This category's OWN manager-label overrides (spec 0080) — never the inherited ones. */
  manager_labels: ManagerLabels
  /** When false the category ignores its ancestry for manager labels (barrier), same shape as the attribute barriers. */
  inherits_manager_labels: boolean
  /** Manager labels resolved from the ancestry chain, excluding this category's own (spec 0080). */
  inherited_manager_labels: ManagerLabels
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
  /** The shared `all` layout this scope falls back to while it has no override of its own (null on the shared scope itself). */
  inherited: LayoutBlob | null
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
  inherits_product_attributes?: boolean
  inherits_quote_attributes?: boolean
  description?: string | null
  attributes?: AttributeAssignmentInput[]
  /** Own business function; omit or null when the category has none of its own (spec 0023). */
  business_function_id?: number | null
  /** Only ever sent for a ROOT category (`parent_id: null`): a child inherits the flag and the server refuses a divergent value. */
  requires_quote?: boolean
  /** Whether the category may be picked as a classification target (spec 0074); omitted on create means selectable. */
  is_selectable?: boolean
  /** Only ever sent for a ROOT category (`parent_id: null`): a child inherits the value and the server refuses a divergent one (spec 0077). */
  management_mode?: CategoryManagementMode
  /** Same root-only rule: only ever sent for a ROOT category, a child inherits it. */
  single_quote_per_opportunity?: boolean
  /** Own manager-label overrides, only valorized positions (spec 0080). */
  manager_labels?: ManagerLabels
  inherits_manager_labels?: boolean
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
