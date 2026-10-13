import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateTaskImportancePayload,
  TaskImportanceDetail,
  TaskImportanceDetailWithPermissions,
  UpdateTaskImportancePayload,
} from '@/features/task-importances/types'

/** Query key of a single task importance's detail, shared by the detail screen and its in-place saves. */
export function taskImportanceDetailQueryKey(id: number) {
  return ['task-importances', 'detail', id] as const
}

/**
 * Fetches a single task importance detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchTaskImportance(id: number): Promise<TaskImportanceDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<TaskImportanceDetail, ResourcePermissions>
  >(`/task-importances/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a task importance. Returns the created resource from the envelope `data`. */
export async function createTaskImportance(payload: CreateTaskImportancePayload): Promise<TaskImportanceDetail> {
  const { data } = await apiClient.post<ApiResponse<TaskImportanceDetail>>('/task-importances', payload)
  return data.data
}

/**
 * Partially updates a task importance (PATCH). `system_key` and `sort_order` are
 * never keys of `UpdateTaskImportancePayload` (spec 0101 `data_contract`): the backend
 * rejects their mere presence with 422. Returns the saved record with the
 * actor's permissions, so the in-place detail re-syncs on it as is.
 */
export async function updateTaskImportance(
  id: number,
  payload: UpdateTaskImportancePayload,
): Promise<TaskImportanceDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<TaskImportanceDetail, ResourcePermissions>
  >(`/task-importances/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Deletes a task importance. The backend answers 409 when the row is still used by
 * a Task (spec 0101 D-8b); both branches are surfaced by the
 * caller (`TaskImportancesTable.runDelete`), not here.
 */
export async function deleteTaskImportance(id: number): Promise<void> {
  await apiClient.delete(`/task-importances/${id}`)
}
