import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { LayoutBlob, LayoutFormScope } from '@/features/attributes/attribute-layout-types'
import type {
  AttributeContext,
  AttributeLayoutData,
  BulkMoveCategoriesPayload,
  BulkMoveCategoriesResult,
  CreateProductCategoryPayload,
  EffectiveAttribute,
  ManagerLabels,
  ProductCategoryDetail,
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
  UpdateProductCategoryPayload,
} from '@/features/product-categories/types'

/** Fetches the full category tree (roots → descendants), for the tree view and the product form's category picker. */
export async function fetchProductCategoryTree(): Promise<ProductCategoryTreeNode[]> {
  const { data } = await apiClient.get<ApiResponse<ProductCategoryTreeNode[]>>(
    '/product-categories/tree',
  )
  return data.data
}

/**
 * Fetches a category's effective attributes (own + every ancestor's) for a
 * single usage context, the source for both the category form's read-only
 * inherited lists and the product form's dynamic attribute fields (spec 0061).
 * Defaults to `'opportunity'` — every pre-existing caller keeps its behavior.
 */
export async function fetchEffectiveAttributes(
  categoryId: number,
  context: AttributeContext = 'opportunity',
): Promise<EffectiveAttribute[]> {
  const { data } = await apiClient.get<ApiResponse<EffectiveAttribute[]>>(
    `/product-categories/${categoryId}/effective-attributes`,
    { params: { context } },
  )
  return data.data
}

/**
 * Fetches a category's effective manager labels (own + every ancestor's,
 * merged position by position) — the form's read-only "inherited" preview
 * for the manager-labels section (spec 0080), same shape as
 * `fetchEffectiveAttributes` for the parent/inherited relationship.
 */
export async function fetchEffectiveManagerLabels(categoryId: number): Promise<ManagerLabels> {
  const { data } = await apiClient.get<ApiResponse<{ manager_labels: ManagerLabels }>>(
    `/product-categories/${categoryId}/effective-manager-labels`,
  )
  return data.data.manager_labels
}

/**
 * Fetches a single category detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchProductCategory(
  id: number,
): Promise<ProductCategoryDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<ProductCategoryDetail, ResourcePermissions>
  >(`/product-categories/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a category. Returns the created resource from the envelope `data`. */
export async function createProductCategory(
  payload: CreateProductCategoryPayload,
): Promise<ProductCategoryDetail> {
  const { data } = await apiClient.post<ApiResponse<ProductCategoryDetail>>(
    '/product-categories',
    payload,
  )
  return data.data
}

/** Partially updates a category (PATCH). Returns the updated resource. */
export async function updateProductCategory(
  id: number,
  payload: UpdateProductCategoryPayload,
): Promise<ProductCategoryDetail> {
  const { data } = await apiClient.patch<ApiResponse<ProductCategoryDetail>>(
    `/product-categories/${id}`,
    payload,
  )
  return data.data
}

/**
 * Moves every category in `category_ids` under `parent_id` (null = root) in a
 * single all-or-nothing operation (spec 0063). A refused batch answers 422
 * with `errors.reason` and the full conflict list, and moves nothing.
 */
export async function bulkMoveProductCategories(
  payload: BulkMoveCategoriesPayload,
): Promise<BulkMoveCategoriesResult> {
  const { data } = await apiClient.post<ApiResponse<BulkMoveCategoriesResult>>(
    '/product-categories/bulk-move',
    payload,
  )
  return data.data
}

/** Deletes a category. Backend responds 204 with no body (409/422 if in use). */
export async function deleteProductCategory(id: number): Promise<void> {
  await apiClient.delete(`/product-categories/${id}`)
}

/**
 * Fetches the persisted attribute layout (or `null`) for one (category,
 * context, scope), the shared layout it inherits while it has none, and the
 * category's effective attribute catalogue — the configurator's load (spec
 * 0062 `data_contract`).
 */
export async function fetchAttributeLayout(
  categoryId: number,
  context: AttributeContext,
  scope: LayoutFormScope,
): Promise<AttributeLayoutData> {
  const { data } = await apiClient.get<ApiResponse<AttributeLayoutData>>(
    `/product-categories/${categoryId}/attribute-layouts`,
    // `exact`: the configurator authors one scope at a time and must see that
    // scope's OWN row — telling an override apart from the shared layout it
    // would otherwise inherit — never the resolution the product form gets.
    { params: { context, form_mode: scope, exact: 1 } },
  )
  return data.data
}

/**
 * Upserts (or, sending an empty `sections` array, deletes) the attribute
 * layout for one (category, context, scope). Returns the persisted,
 * normalized layout (`null` once deleted — on a per-mode scope that is the
 * "back to the shared layout" reset).
 */
export async function saveAttributeLayout(
  categoryId: number,
  context: AttributeContext,
  scope: LayoutFormScope,
  layout: LayoutBlob,
): Promise<LayoutBlob | null> {
  const { data } = await apiClient.put<ApiResponse<{ layout: LayoutBlob | null }>>(
    `/product-categories/${categoryId}/attribute-layouts`,
    { context, form_mode: scope, layout },
  )
  return data.data.layout
}
