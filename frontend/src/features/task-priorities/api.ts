import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateTaskPriorityPayload,
  TaskPriorityDetail,
  TaskPriorityDetailWithPermissions,
  UpdateTaskPriorityPayload,
} from '@/features/task-priorities/types'

/**
 * Fetches a single task priority detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchTaskPriority(id: number): Promise<TaskPriorityDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<TaskPriorityDetail, ResourcePermissions>
  >(`/task-priorities/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a task priority. Returns the created resource from the envelope `data`. */
export async function createTaskPriority(payload: CreateTaskPriorityPayload): Promise<TaskPriorityDetail> {
  const { data } = await apiClient.post<ApiResponse<TaskPriorityDetail>>('/task-priorities', payload)
  return data.data
}

/**
 * Partially updates a task priority (PATCH). `system_key` and `sort_order` are
 * never keys of `UpdateTaskPriorityPayload` (spec 0101 `data_contract`): the backend
 * rejects their mere presence with 422.
 */
export async function updateTaskPriority(
  id: number,
  payload: UpdateTaskPriorityPayload,
): Promise<TaskPriorityDetail> {
  const { data } = await apiClient.patch<ApiResponse<TaskPriorityDetail>>(`/task-priorities/${id}`, payload)
  return data.data
}

/**
 * Deletes a task priority. The backend answers 409 when the row is still used by
 * a Task (spec 0101 D-8b); both branches are surfaced by the
 * caller (`TaskPrioritiesTable.runDelete`), not here.
 */
export async function deleteTaskPriority(id: number): Promise<void> {
  await apiClient.delete(`/task-priorities/${id}`)
}
