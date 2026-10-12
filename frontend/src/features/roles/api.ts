import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateRolePayload,
  RoleDetail,
  RoleDetailWithPermissions,
  UpdateRolePayload,
} from '@/features/roles/types'

/** Cache key of a role's detail, shared by the fetch and the in-place save that seeds it. */
export function roleDetailQueryKey(id: number) {
  return ['roles', 'detail', id] as const
}

/**
 * Fetches a single role detail together with the actor's authorization
 * metadata for it (the envelope's top-level `permissions` sibling of `data`,
 * exposed as `authorization` here — `data.permissions` is already the role's
 * own granted-permission names and must not be shadowed).
 */
export async function fetchRole(id: number): Promise<RoleDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<RoleDetail, ResourcePermissions>
  >(`/roles/${id}`)
  return { ...data.data, authorization: data.permissions }
}

/** Creates a role. Returns the created resource from the envelope `data`. */
export async function createRole(
  payload: CreateRolePayload,
): Promise<RoleDetail> {
  const { data } = await apiClient.post<ApiResponse<RoleDetail>>(
    '/roles',
    payload,
  )
  return data.data
}

/**
 * Partially updates a role (PATCH). Returns the updated resource with the
 * actor's authorization metadata, so the in-place detail can replace its
 * cached copy without a second request.
 */
export async function updateRole(
  id: number,
  payload: UpdateRolePayload,
): Promise<RoleDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<RoleDetail, ResourcePermissions>
  >(`/roles/${id}`, payload)
  return { ...data.data, authorization: data.permissions }
}

/** Deletes a role. Backend responds 204 with no body. */
export async function deleteRole(id: number): Promise<void> {
  await apiClient.delete(`/roles/${id}`)
}
