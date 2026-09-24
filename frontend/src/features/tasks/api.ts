import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CompleteTaskPayload,
  CreateTaskPayload,
  RequestTaskUpdatePayload,
  TaskDetail,
  TaskDetailWithPermissions,
  UpdateTaskPayload,
} from '@/features/tasks/types'

/** Table/module domain key of this module, shared by every adapter (mirrors `WORK_ORDERS_DOMAIN`). */
export const TASKS_DOMAIN = 'tasks'

/**
 * Polymorphic owner alias sent as `attachable_type` (spec 0117), per
 * `config('attachments.attachable_types')`. SINGULAR, unlike the plural
 * domain key above: that one is the authorization vocabulary, this one the
 * record's morph identity. Mirrors `CONTRACT_ATTACHABLE_ALIAS`.
 */
export const TASK_ATTACHABLE_ALIAS = 'task'

/** Query key for a single task's detail (fresh-on-open pattern). */
export function taskDetailQueryKey(id: number) {
  return ['tasks', 'detail', id] as const
}

/** Unwraps `{ data, permissions }` into the flat shape every single-task read/write returns. */
function withPermissions(
  response: ApiResponseWithPermissions<TaskDetail, ResourcePermissions>,
): TaskDetailWithPermissions {
  return { ...response.data, permissions: response.permissions }
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
  return withPermissions(data)
}

/** Creates a task. `creator_id` is set server-side from the actor (D-10). */
export async function createTask(payload: CreateTaskPayload): Promise<TaskDetail> {
  const { data } = await apiClient.post<ApiResponse<TaskDetail>>('/tasks', payload)
  return data.data
}

/**
 * Partially updates a task (PATCH). `creator_id`/`completion_percentage`/
 * `is_blocked` are never keys of `UpdateTaskPayload` (D-6/D-10): the backend
 * rejects their mere presence with 422 regardless of role.
 */
export async function updateTask(id: number, payload: UpdateTaskPayload): Promise<TaskDetail> {
  const { data } = await apiClient.patch<ApiResponse<TaskDetail>>(`/tasks/${id}`, payload)
  return data.data
}

/** Deletes a task. 204 with no body; 409 when it still has sub-tasks (D-8a). */
export async function deleteTask(id: number): Promise<void> {
  await apiClient.delete(`/tasks/${id}`)
}

/**
 * "Completa" (spec 0116 D-8). CASO 1 (chiusura): omits `validation_status_id`,
 * the task lands on the protected `closed_positive` row. CASO 2 (richiedi
 * validazione): sends it, the task moves to that `in_validation` row instead.
 * 409 when the task `is_blocked`; 422 on the missing feedback or wrong phase
 * (`TaskActionAvailability::isCompletable`).
 */
export async function completeTask(
  id: number,
  payload: CompleteTaskPayload,
): Promise<TaskDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<TaskDetail, ResourcePermissions>>(
    `/tasks/${id}/complete`,
    payload,
  )
  return withPermissions(data)
}

/** "Riapri" (D-8): moves the task back to the designated `in_progress` row, clearing feedback/completion date. */
export async function uncompleteTask(id: number): Promise<TaskDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<TaskDetail, ResourcePermissions>>(
    `/tasks/${id}/uncomplete`,
  )
  return withPermissions(data)
}

/** "Approva validazione" (D-8): moves the task to the protected `closed_positive` row. */
export async function approveTask(id: number): Promise<TaskDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<TaskDetail, ResourcePermissions>>(
    `/tasks/${id}/approve`,
  )
  return withPermissions(data)
}

/** "Rifiuta validazione" (D-8): moves the task back to `in_progress`; `closure_feedback` is kept as the motivation. */
export async function rejectTask(id: number): Promise<TaskDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<TaskDetail, ResourcePermissions>>(
    `/tasks/${id}/reject`,
  )
  return withPermissions(data)
}

/** "Blocca" (D-8): sets `is_blocked = true`. 422 when already blocked (`isBlockable`). */
export async function blockTask(id: number): Promise<TaskDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<TaskDetail, ResourcePermissions>>(
    `/tasks/${id}/block`,
  )
  return withPermissions(data)
}

/** "Sblocca" (D-8): sets `is_blocked = false`. 422 when not blocked (`isUnblockable`). */
export async function unblockTask(id: number): Promise<TaskDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<TaskDetail, ResourcePermissions>>(
    `/tasks/${id}/unblock`,
  )
  return withPermissions(data)
}

/**
 * "Richiedi aggiornamento" (spec 0153 D-14): notifies a fixed recipient
 * group (`target`) by mail and in-app notification, with a mandatory
 * message. Writes nothing on the task — the response is the same detail
 * tree as every other action, never a shape of its own.
 */
export async function requestTaskUpdate(
  id: number,
  payload: RequestTaskUpdatePayload,
): Promise<TaskDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<TaskDetail, ResourcePermissions>>(
    `/tasks/${id}/request-update`,
    payload,
  )
  return withPermissions(data)
}
