import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateProductPayload,
  ProductDetail,
  ProductDetailWithPermissions,
  UpdateProductPayload,
} from '@/features/products/types'

/**
 * Query key of a single product's detail (fresh-on-open pattern). Shared by
 * the detail/edit pages and by the post-mutation invalidation, so they can
 * never drift apart. `null` (an unparsable route param) is a key that is never
 * fetched.
 */
export function productDetailQueryKey(id: number | null) {
  return ['products', 'detail', id] as const
}

/**
 * Fetches a single product detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchProduct(id: number): Promise<ProductDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<ProductDetail, ResourcePermissions>
  >(`/products/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/**
 * The next sequential code (PRD-0001...) suggested for the create form's
 * `code` auto-fill (spec 0065, mirrors `fetchProjectNextCode`). Non-binding:
 * the user may edit it, and the server resolves the definitive value
 * atomically on store.
 */
export async function fetchProductNextCode(): Promise<string> {
  const { data } = await apiClient.get<ApiResponse<{ code: string }>>('/products/next-code')
  return data.data.code
}

/** Creates a product. Returns the created resource from the envelope `data`. */
export async function createProduct(payload: CreateProductPayload): Promise<ProductDetail> {
  const { data } = await apiClient.post<ApiResponse<ProductDetail>>('/products', payload)
  return data.data
}

/** Partially updates a product (PATCH). Returns the updated resource. */
export async function updateProduct(
  id: number,
  payload: UpdateProductPayload,
): Promise<ProductDetail> {
  const { data } = await apiClient.patch<ApiResponse<ProductDetail>>(`/products/${id}`, payload)
  return data.data
}

/** Deletes a product. Backend responds 204 with no body. */
export async function deleteProduct(id: number): Promise<void> {
  await apiClient.delete(`/products/${id}`)
}
