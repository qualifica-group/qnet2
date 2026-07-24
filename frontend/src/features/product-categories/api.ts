import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { LayoutBlob, LayoutFormMode } from '@/features/attributes/attribute-layout-types'
import type {
  AttributeContext,
  AttributeLayoutData,
  CreateProductCategoryPayload,
  EffectiveAttribute,
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

/** Deletes a category. Backend responds 204 with no body (409/422 if in use). */
export async function deleteProductCategory(id: number): Promise<void> {
  await apiClient.delete(`/product-categories/${id}`)
}

/**
 * Fetches the persisted attribute layout (or `null`, flat fallback) for one
 * (category, context, form_mode), plus its effective attribute catalogue —
 * the configurator's load (spec 0062 `data_contract`).
 */
export async function fetchAttributeLayout(
  categoryId: number,
  context: AttributeContext,
  formMode: LayoutFormMode,
): Promise<AttributeLayoutData> {
  const { data } = await apiClient.get<ApiResponse<AttributeLayoutData>>(
    `/product-categories/${categoryId}/attribute-layouts`,
    { params: { context, form_mode: formMode } },
  )
  return data.data
}

/**
 * Upserts (or, sending an empty `sections` array, deletes) the attribute
 * layout for one (category, context, form_mode). Returns the persisted,
 * normalized layout (`null` once deleted).
 */
export async function saveAttributeLayout(
  categoryId: number,
  context: AttributeContext,
  formMode: LayoutFormMode,
  layout: LayoutBlob,
): Promise<LayoutBlob | null> {
  const { data } = await apiClient.put<ApiResponse<{ layout: LayoutBlob | null }>>(
    `/product-categories/${categoryId}/attribute-layouts`,
    { context, form_mode: formMode, layout },
  )
  return data.data.layout
}
