import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateTaskStatusPayload,
  TaskStatusDetail,
  TaskStatusDetailWithPermissions,
  UpdateTaskStatusPayload,
} from '@/features/task-statuses/types'

/**
 * Fetches a single task status detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchTaskStatus(id: number): Promise<TaskStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<TaskStatusDetail, ResourcePermissions>
  >(`/task-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a task status. Returns the created resource from the envelope `data`. */
export async function createTaskStatus(payload: CreateTaskStatusPayload): Promise<TaskStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<TaskStatusDetail>>('/task-statuses', payload)
  return data.data
}

/**
 * Partially updates a task status (PATCH). `system_key` and `sort_order` are
 * never keys of `UpdateTaskStatusPayload` (spec 0101 `data_contract`): the backend
 * rejects their mere presence with 422.
 */
export async function updateTaskStatus(
  id: number,
  payload: UpdateTaskStatusPayload,
): Promise<TaskStatusDetail> {
  const { data } = await apiClient.patch<ApiResponse<TaskStatusDetail>>(`/task-statuses/${id}`, payload)
  return data.data
}

/**
 * Deletes a task status. The backend answers 409 when the row is still used by
 * a Task (spec 0101 D-8b), and 422 on a system row (D-8c); both branches are surfaced by the
 * caller (`TaskStatusesTable.runDelete`), not here.
 */
export async function deleteTaskStatus(id: number): Promise<void> {
  await apiClient.delete(`/task-statuses/${id}`)
}
