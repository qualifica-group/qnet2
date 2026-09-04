import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateTaskCategoryPayload,
  TaskCategoryDetail,
  TaskCategoryDetailWithPermissions,
  UpdateTaskCategoryPayload,
} from '@/features/task-categories/types'

/**
 * Fetches a single task category detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchTaskCategory(id: number): Promise<TaskCategoryDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<TaskCategoryDetail, ResourcePermissions>
  >(`/task-categories/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a task category. Returns the created resource from the envelope `data`. */
export async function createTaskCategory(payload: CreateTaskCategoryPayload): Promise<TaskCategoryDetail> {
  const { data } = await apiClient.post<ApiResponse<TaskCategoryDetail>>('/task-categories', payload)
  return data.data
}

/**
 * Partially updates a task category (PATCH). `system_key` and `sort_order` are
 * never keys of `UpdateTaskCategoryPayload` (spec 0101 `data_contract`): the backend
 * rejects their mere presence with 422.
 */
export async function updateTaskCategory(
  id: number,
  payload: UpdateTaskCategoryPayload,
): Promise<TaskCategoryDetail> {
  const { data } = await apiClient.patch<ApiResponse<TaskCategoryDetail>>(`/task-categories/${id}`, payload)
  return data.data
}

/**
 * Deletes a task category. The backend answers 409 when the row is still used by
 * a Task (spec 0101 D-8b); both branches are surfaced by the
 * caller (`TaskCategoriesTable.runDelete`), not here.
 */
export async function deleteTaskCategory(id: number): Promise<void> {
  await apiClient.delete(`/task-categories/${id}`)
}
