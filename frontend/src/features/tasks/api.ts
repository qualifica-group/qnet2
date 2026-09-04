import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateTaskPayload,
  TaskDetail,
  TaskDetailWithPermissions,
  UpdateTaskPayload,
} from '@/features/tasks/types'

/** Table/module domain key of this module, shared by every adapter (mirrors `WORK_ORDERS_DOMAIN`). */
export const TASKS_DOMAIN = 'tasks'

/** Query key for a single task's detail (fresh-on-open pattern). */
export function taskDetailQueryKey(id: number) {
  return ['tasks', 'detail', id] as const
}

/**
 * Fetches a single task detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 * 403 when the actor is outside the visibility scope (D-9).
 */
export async function fetchTask(id: number): Promise<TaskDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<TaskDetail, ResourcePermissions>>(
    `/tasks/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Creates a task. `creator_id` is set server-side from the actor (D-10). */
export async function createTask(payload: CreateTaskPayload): Promise<TaskDetail> {
  const { data } = await apiClient.post<ApiResponse<TaskDetail>>('/tasks', payload)
  return data.data
}

/**
 * Partially updates a task (PATCH). `creator_id`/`completion_percentage` are
 * never keys of `UpdateTaskPayload` (D-6/D-10): the backend rejects their mere
 * presence with 422 regardless of role.
 */
export async function updateTask(id: number, payload: UpdateTaskPayload): Promise<TaskDetail> {
  const { data } = await apiClient.patch<ApiResponse<TaskDetail>>(`/tasks/${id}`, payload)
  return data.data
}

/** Deletes a task. 204 with no body; 409 when it still has sub-tasks (D-8a). */
export async function deleteTask(id: number): Promise<void> {
  await apiClient.delete(`/tasks/${id}`)
}
