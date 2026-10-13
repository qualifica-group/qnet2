import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateReferentPayload,
  ReferentDetail,
  ReferentDetailWithPermissions,
  UpdateReferentPayload,
} from '@/features/referents/types'

/**
 * Query key of a single referent's detail (fresh-on-open pattern). Shared by
 * the detail page and by the post-mutation invalidation, so they can
 * never drift apart. `null` (an unparsable route param) is a key that is never
 * fetched.
 */
export function referentDetailQueryKey(id: number | null) {
  return ['referents', 'detail', id] as const
}

/**
 * Fetches a single referent detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchReferent(id: number): Promise<ReferentDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<ReferentDetail, ResourcePermissions>
  >(`/referents/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a referent. Returns the created resource from the envelope `data`. */
export async function createReferent(
  payload: CreateReferentPayload,
): Promise<ReferentDetail> {
  const { data } = await apiClient.post<ApiResponse<ReferentDetail>>('/referents', payload)
  return data.data
}

/**
 * Partially updates a referent (PATCH). Returns the updated resource with the
 * actor's re-authorized `permissions`, so the in-place detail (spec 0216) can
 * seed its cache with the full shape.
 */
export async function updateReferent(
  id: number,
  payload: UpdateReferentPayload,
): Promise<ReferentDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<ReferentDetail, ResourcePermissions>
  >(`/referents/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a referent. Backend responds 204 with no body. */
export async function deleteReferent(id: number): Promise<void> {
  await apiClient.delete(`/referents/${id}`)
}
