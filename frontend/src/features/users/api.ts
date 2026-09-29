import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateUserPayload,
  UpdateUserPayload,
  UserDetail,
  UserDetailWithPermissions,
} from '@/features/users/types'

/**
 * Fetches a single user detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchUser(id: number): Promise<UserDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<UserDetail, ResourcePermissions>
  >(`/users/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a user. Returns the created resource from the envelope `data`. */
export async function createUser(
  payload: CreateUserPayload,
): Promise<UserDetail> {
  const { data } = await apiClient.post<ApiResponse<UserDetail>>(
    '/users',
    payload,
  )
  return data.data
}

/** Partially updates a user (PATCH). Returns the updated resource. */
export async function updateUser(
  id: number,
  payload: UpdateUserPayload,
): Promise<UserDetail> {
  const { data } = await apiClient.patch<ApiResponse<UserDetail>>(
    `/users/${id}`,
    payload,
  )
  return data.data
}

/** Re-sends the welcome (invite) email to a user awaiting first access (spec 0177). Returns the backend message. */
export async function resendWelcomeEmail(id: number): Promise<string | null> {
  const { data } = await apiClient.post<ApiResponse<null>>(`/users/${id}/resend-welcome`)
  return data.message || null
}

/** Deletes a user. Backend responds 204 with no body. */
export async function deleteUser(id: number): Promise<void> {
  await apiClient.delete(`/users/${id}`)
}

/** Uploads a user's avatar (multipart). Returns the updated resource. */
export async function uploadUserAvatar(
  id: number,
  file: File,
): Promise<UserDetail> {
  const formData = new FormData()
  formData.append('avatar', file)
  const { data } = await apiClient.post<ApiResponse<UserDetail>>(
    `/users/${id}/avatar`,
    formData,
  )
  return data.data
}

/** Removes a user's avatar. Returns the updated resource. */
export async function deleteUserAvatar(id: number): Promise<UserDetail> {
  const { data } = await apiClient.delete<ApiResponse<UserDetail>>(
    `/users/${id}/avatar`,
  )
  return data.data
}
