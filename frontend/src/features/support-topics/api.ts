import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateSupportTopicPayload,
  SupportTopicDetail,
  SupportTopicDetailWithPermissions,
  UpdateSupportTopicPayload,
} from '@/features/support-topics/types'

/** Query key of a single support topic's detail, shared by the detail screen and its in-place saves. */
export function supportTopicDetailQueryKey(id: number) {
  return ['support-topics', 'detail', id] as const
}

/** Fetches a support topic with the actor's authorization metadata (`permissions`, sibling of `data`). */
export async function fetchSupportTopic(id: number): Promise<SupportTopicDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<SupportTopicDetail, ResourcePermissions>>(
    `/support-topics/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

export async function createSupportTopic(payload: CreateSupportTopicPayload): Promise<SupportTopicDetail> {
  const { data } = await apiClient.post<ApiResponse<SupportTopicDetail>>('/support-topics', payload)
  return data.data
}

/** Partial update (PATCH): returns the saved record with the actor's permissions. */
export async function updateSupportTopic(
  id: number,
  payload: UpdateSupportTopicPayload,
): Promise<SupportTopicDetailWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<SupportTopicDetail, ResourcePermissions>>(
    `/support-topics/${id}`,
    payload,
  )
  return { ...data.data, permissions: data.permissions }
}

export async function deleteSupportTopic(id: number): Promise<void> {
  await apiClient.delete(`/support-topics/${id}`)
}
