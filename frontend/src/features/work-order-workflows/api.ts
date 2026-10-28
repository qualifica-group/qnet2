import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateWorkOrderWorkflowPayload,
  CriterionFieldOption,
  UpdateDefaultStatusesPayload,
  UpdateWorkOrderWorkflowPayload,
  WorkOrderWorkflowDetail,
  WorkOrderWorkflowDetailWithPermissions,
  WorkOrderWorkflowStatusItem,
} from '@/features/work-order-workflows/types'

const BASE_PATH = '/work-order-workflows'

/** Centralized query keys of the work order workflow configurator. */
export const workOrderWorkflowKeys = {
  detail: (id: number) => ['work-order-workflows', 'detail', id] as const,
  criterionFields: ['work-order-workflows', 'criterion-fields'] as const,
  defaultStatuses: ['work-order-workflows', 'default-statuses'] as const,
}

/** Fetches a workflow detail together with the actor's authorization metadata (`permissions`). */
export async function fetchWorkOrderWorkflow(id: number): Promise<WorkOrderWorkflowDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<WorkOrderWorkflowDetail, ResourcePermissions>>(
    `${BASE_PATH}/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Creates a workflow (criteria + statuses in one request). */
export async function createWorkOrderWorkflow(payload: CreateWorkOrderWorkflowPayload): Promise<WorkOrderWorkflowDetail> {
  const { data } = await apiClient.post<ApiResponse<WorkOrderWorkflowDetail>>(BASE_PATH, payload)
  return data.data
}

/** Updates a workflow; returns the saved record with the actor's permissions so the detail re-syncs on it. */
export async function updateWorkOrderWorkflow(
  id: number,
  payload: UpdateWorkOrderWorkflowPayload,
): Promise<WorkOrderWorkflowDetailWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<WorkOrderWorkflowDetail, ResourcePermissions>>(
    `${BASE_PATH}/${id}`,
    payload,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a workflow; the backend re-resolves the impacted work orders in the same transaction. */
export async function deleteWorkOrderWorkflow(id: number): Promise<void> {
  await apiClient.delete(`${BASE_PATH}/${id}`)
}

/** Fetches the allow-listed criterion fields, for the criteria editor's field select. */
export async function fetchCriterionFields(): Promise<CriterionFieldOption[]> {
  const { data } = await apiClient.get<ApiResponse<CriterionFieldOption[]>>(`${BASE_PATH}/criterion-fields`)
  return data.data
}

/** Fetches the GLOBAL default status set, ordered by `sort_order`. */
export async function fetchDefaultStatuses(): Promise<WorkOrderWorkflowStatusItem[]> {
  const { data } = await apiClient.get<ApiResponse<WorkOrderWorkflowStatusItem[]>>(`${BASE_PATH}/default-statuses`)
  return data.data
}

/** Syncs the GLOBAL default set; returns the fresh, full ordered list. */
export async function updateDefaultStatuses(payload: UpdateDefaultStatusesPayload): Promise<WorkOrderWorkflowStatusItem[]> {
  const { data } = await apiClient.put<ApiResponse<WorkOrderWorkflowStatusItem[]>>(`${BASE_PATH}/default-statuses`, payload)
  return data.data
}
