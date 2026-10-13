import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateTagPayload,
  TagDetail,
  TagDetailWithPermissions,
  UpdateTagPayload,
} from '@/features/tags/types'

/** Query key of a single tag's detail, shared by the detail screen and its in-place saves. */
export function tagDetailQueryKey(id: number) {
  return ['tags', 'detail', id] as const
}

/**
 * Fetches a single tag detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchTag(id: number): Promise<TagDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<TagDetail, ResourcePermissions>>(
    `/tags/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Creates a tag. Returns the created resource from the envelope `data`. */
export async function createTag(payload: CreateTagPayload): Promise<TagDetail> {
  const { data } = await apiClient.post<ApiResponse<TagDetail>>('/tags', payload)
  return data.data
}

/**
 * Partially updates a tag (PATCH). Returns the saved record with the
 * actor's permissions, so the in-place detail re-syncs on it as is.
 */
export async function updateTag(
  id: number,
  payload: UpdateTagPayload,
): Promise<TagDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<TagDetail, ResourcePermissions>
  >(`/tags/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a tag. Backend responds 204 with no body; 409 when still in use. */
export async function deleteTag(id: number): Promise<void> {
  await apiClient.delete(`/tags/${id}`)
}
