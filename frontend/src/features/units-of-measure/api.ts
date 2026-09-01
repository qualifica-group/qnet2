import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateUnitOfMeasurePayload,
  UnitOfMeasureDetail,
  UnitOfMeasureDetailWithPermissions,
  UpdateUnitOfMeasurePayload,
} from '@/features/units-of-measure/types'

/**
 * Fetches a single unit of measure detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchUnitOfMeasure(id: number): Promise<UnitOfMeasureDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<UnitOfMeasureDetail, ResourcePermissions>
  >(`/units-of-measure/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a unit of measure. Returns the created resource from the envelope `data`. */
export async function createUnitOfMeasure(
  payload: CreateUnitOfMeasurePayload,
): Promise<UnitOfMeasureDetail> {
  const { data } = await apiClient.post<ApiResponse<UnitOfMeasureDetail>>(
    '/units-of-measure',
    payload,
  )
  return data.data
}

/**
 * Partially updates a unit of measure (PATCH). `code` is never a key of
 * `UpdateUnitOfMeasurePayload` (D-1): the backend rejects its mere presence
 * with 422 regardless of role. Returns the updated resource.
 */
export async function updateUnitOfMeasure(
  id: number,
  payload: UpdateUnitOfMeasurePayload,
): Promise<UnitOfMeasureDetail> {
  const { data } = await apiClient.patch<ApiResponse<UnitOfMeasureDetail>>(
    `/units-of-measure/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a unit of measure. Backend responds 204 with no body, or 409 when
 * the unit is still used by a product or a quote line (D-7); the 409 branch
 * is handled by the caller (`UnitsOfMeasureTable.runDelete`), not here.
 */
export async function deleteUnitOfMeasure(id: number): Promise<void> {
  await apiClient.delete(`/units-of-measure/${id}`)
}
