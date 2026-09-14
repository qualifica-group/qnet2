import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateTaskTemplatePayload,
  TaskTemplateDetail,
  TaskTemplateDetailWithPermissions,
  UpdateTaskTemplatePayload,
} from '@/features/task-templates/types'

/**
 * Fetches a single task template detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchTaskTemplate(id: number): Promise<TaskTemplateDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<TaskTemplateDetail, ResourcePermissions>
  >(`/task-templates/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a task template with its ordered `items[]`. Returns the created resource from the envelope `data`. */
export async function createTaskTemplate(payload: CreateTaskTemplatePayload): Promise<TaskTemplateDetail> {
  const { data } = await apiClient.post<ApiResponse<TaskTemplateDetail>>('/task-templates', payload)
  return data.data
}

/**
 * Partially updates a task template (PATCH). `items`, when sent, is a full
 * authoritative sync of the rows (a row missing from the array is deleted
 * server-side, D-1/AC-004). Returns the updated resource.
 */
export async function updateTaskTemplate(
  id: number,
  payload: UpdateTaskTemplatePayload,
): Promise<TaskTemplateDetail> {
  const { data } = await apiClient.patch<ApiResponse<TaskTemplateDetail>>(`/task-templates/${id}`, payload)
  return data.data
}

/**
 * Deletes a task template. Backend responds 204 with no body, or 409 when
 * the template is referenced by `work_orders.task_template_id` (D-5); the
 * 409 branch is handled by the caller (`TaskTemplatesTable.runDelete`), not
 * here.
 */
export async function deleteTaskTemplate(id: number): Promise<void> {
  await apiClient.delete(`/task-templates/${id}`)
}
