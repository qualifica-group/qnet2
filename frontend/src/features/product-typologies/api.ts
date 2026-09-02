import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateProductTypologyPayload,
  ProductTypologyDetail,
  ProductTypologyDetailWithPermissions,
  UpdateProductTypologyPayload,
} from '@/features/product-typologies/types'

/**
 * Fetches a single product typology detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchProductTypology(id: number): Promise<ProductTypologyDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<ProductTypologyDetail, ResourcePermissions>
  >(`/product-typologies/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a product typology. Returns the created resource from the envelope `data`. */
export async function createProductTypology(
  payload: CreateProductTypologyPayload,
): Promise<ProductTypologyDetail> {
  const { data } = await apiClient.post<ApiResponse<ProductTypologyDetail>>(
    '/product-typologies',
    payload,
  )
  return data.data
}

/**
 * Partially updates a product typology (PATCH). `code` is never a key of
 * `UpdateProductTypologyPayload` (D-1): the backend rejects its mere presence
 * with 422 regardless of role. Returns the updated resource.
 */
export async function updateProductTypology(
  id: number,
  payload: UpdateProductTypologyPayload,
): Promise<ProductTypologyDetail> {
  const { data } = await apiClient.patch<ApiResponse<ProductTypologyDetail>>(
    `/product-typologies/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a product typology. Backend responds 204 with no body, or 409 when
 * the unit is still used by a product or a quote line (D-7); the 409 branch
 * is handled by the caller (`ProductTypologiesTable.runDelete`), not here.
 */
export async function deleteProductTypology(id: number): Promise<void> {
  await apiClient.delete(`/product-typologies/${id}`)
}
