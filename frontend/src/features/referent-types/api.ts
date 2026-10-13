import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateReferentTypePayload,
  ReferentTypeDetail,
  ReferentTypeDetailWithPermissions,
  UpdateReferentTypePayload,
} from '@/features/referent-types/types'

/** Query key of a single referent type's detail, shared by the detail screen and its in-place saves. */
export function referentTypeDetailQueryKey(id: number) {
  return ['referent-types', 'detail', id] as const
}

/**
 * Fetches a single referent-type detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchReferentType(
  id: number,
): Promise<ReferentTypeDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<ReferentTypeDetail, ResourcePermissions>
  >(`/referent-types/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a referent type. Returns the created resource from the envelope `data`. */
export async function createReferentType(
  payload: CreateReferentTypePayload,
): Promise<ReferentTypeDetail> {
  const { data } = await apiClient.post<ApiResponse<ReferentTypeDetail>>(
    '/referent-types',
    payload,
  )
  return data.data
}

/**
 * Partially updates a referent type (PATCH). Returns the saved record with the
 * actor's permissions, so the in-place detail re-syncs on it as is.
 */
export async function updateReferentType(
  id: number,
  payload: UpdateReferentTypePayload,
): Promise<ReferentTypeDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<ReferentTypeDetail, ResourcePermissions>
  >(`/referent-types/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a referent type. Backend responds 204 with no body. */
export async function deleteReferentType(id: number): Promise<void> {
  await apiClient.delete(`/referent-types/${id}`)
}
