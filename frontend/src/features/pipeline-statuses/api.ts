import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreatePipelineStatusPayload,
  PipelineStatusDetail,
  PipelineStatusDetailWithPermissions,
  UpdatePipelineStatusPayload,
} from '@/features/pipeline-statuses/types'

/** Query key of a single project status's detail, shared by the detail screen and its in-place saves. */
export function pipelineStatusDetailQueryKey(id: number) {
  return ['pipeline-statuses', 'detail', id] as const
}

/**
 * Fetches a single project status detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchPipelineStatus(id: number): Promise<PipelineStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<PipelineStatusDetail, ResourcePermissions>
  >(`/pipeline-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a project status. Returns the created resource from the envelope `data`. */
export async function createPipelineStatus(
  payload: CreatePipelineStatusPayload,
): Promise<PipelineStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<PipelineStatusDetail>>(
    '/pipeline-statuses',
    payload,
  )
  return data.data
}

/**
 * Partially updates a project status (PATCH). Returns the saved record with the actor's
 * permissions, so the in-place detail re-syncs on it as is.
 */
export async function updatePipelineStatus(
  id: number,
  payload: UpdatePipelineStatusPayload,
): Promise<PipelineStatusDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<PipelineStatusDetail, ResourcePermissions>
  >(`/pipeline-statuses/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Deletes a project status. Backend responds 204 with no body, or 409 when
 * the status is still referenced by a Project or a Campaign (BR-4) — the
 * caller surfaces the backend's `message` for that case.
 */
export async function deletePipelineStatus(id: number): Promise<void> {
  await apiClient.delete(`/pipeline-statuses/${id}`)
}
