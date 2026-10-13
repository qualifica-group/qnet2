import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateTaskTypePayload,
  TaskTypeDetail,
  TaskTypeDetailWithPermissions,
  UpdateTaskTypePayload,
} from '@/features/task-types/types'

/** Query key of a single task type's detail, shared by the detail screen and its in-place saves. */
export function taskTypeDetailQueryKey(id: number) {
  return ['task-types', 'detail', id] as const
}

/**
 * Fetches a single task type detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchTaskType(id: number): Promise<TaskTypeDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<TaskTypeDetail, ResourcePermissions>
  >(`/task-types/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a task type. Returns the created resource from the envelope `data`. */
export async function createTaskType(payload: CreateTaskTypePayload): Promise<TaskTypeDetail> {
  const { data } = await apiClient.post<ApiResponse<TaskTypeDetail>>('/task-types', payload)
  return data.data
}

/**
 * Partially updates a task type (PATCH). `system_key` and `sort_order` are
 * never keys of `UpdateTaskTypePayload` (spec 0101 `data_contract`): the backend
 * rejects their mere presence with 422. Returns the saved record with the
 * actor's permissions, so the in-place detail re-syncs on it as is.
 */
export async function updateTaskType(
  id: number,
  payload: UpdateTaskTypePayload,
): Promise<TaskTypeDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<TaskTypeDetail, ResourcePermissions>
  >(`/task-types/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Deletes a task type. The backend answers 409 when the row is still used by
 * a Task (spec 0101 D-8b); both branches are surfaced by the
 * caller (`TaskTypesTable.runDelete`), not here.
 */
export async function deleteTaskType(id: number): Promise<void> {
  await apiClient.delete(`/task-types/${id}`)
}
