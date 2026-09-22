import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  BulkTaskPayload,
  BulkTaskResult,
  MoveBoardTaskPayload,
  MoveBoardTaskResult,
  TaskBoardPayload,
  WorkOrderStage,
} from '@/features/work-orders/task-board/types'

/** GET /work-orders/{id}/task-board (D-9, AC-011/AC-012): the board's single payload — stages, every visible task, and whether the commessa is read-only. */
export async function fetchTaskBoard(workOrderId: number): Promise<TaskBoardPayload> {
  const { data } = await apiClient.get<ApiResponse<TaskBoardPayload>>(
    `/work-orders/${workOrderId}/task-board`,
  )
  return data.data
}

/** GET /work-orders/{id}/stages: the standalone phase list feeding the task form's "Fase" select (AC-030 narrows to open ones client-side). */
export async function fetchWorkOrderStages(workOrderId: number): Promise<WorkOrderStage[]> {
  const { data } = await apiClient.get<ApiResponse<WorkOrderStage[]>>(
    `/work-orders/${workOrderId}/stages`,
  )
  return data.data
}

/** POST /work-orders/{id}/stages: appends a new fase at the end (D-2). 409 when the commessa is closed. */
export async function createWorkOrderStage(workOrderId: number, name: string): Promise<WorkOrderStage> {
  const { data } = await apiClient.post<ApiResponse<WorkOrderStage>>(
    `/work-orders/${workOrderId}/stages`,
    { name },
  )
  return data.data
}

/** PATCH /work-orders/{id}/stages/{stage}: renames a fase. 404 when the fase belongs to another commessa (scopeBindings). */
export async function renameWorkOrderStage(
  workOrderId: number,
  stageId: number,
  name: string,
): Promise<WorkOrderStage> {
  const { data } = await apiClient.patch<ApiResponse<WorkOrderStage>>(
    `/work-orders/${workOrderId}/stages/${stageId}`,
    { name },
  )
  return data.data
}

/** DELETE /work-orders/{id}/stages/{stage}: its tasks fall back to "Senza fase", appended at the end (D-2/AC-005). */
export async function deleteWorkOrderStage(workOrderId: number, stageId: number): Promise<void> {
  await apiClient.delete(`/work-orders/${workOrderId}/stages/${stageId}`)
}

/** POST /work-orders/{id}/stages/reorder: `stageIds` must be an EXACT permutation of the commessa's own fasi (422 otherwise, AC-006). */
export async function reorderWorkOrderStages(
  workOrderId: number,
  stageIds: number[],
): Promise<WorkOrderStage[]> {
  const { data } = await apiClient.post<ApiResponse<WorkOrderStage[]>>(
    `/work-orders/${workOrderId}/stages/reorder`,
    { stage_ids: stageIds },
  )
  return data.data
}

/** POST /work-orders/{id}/stages/{stage}/close: 409 when the fase still holds a non-closed task, root or sub-task (D-4/AC-008) — see `CloseStageConflictError`. */
export async function closeWorkOrderStage(workOrderId: number, stageId: number): Promise<WorkOrderStage> {
  const { data } = await apiClient.post<ApiResponse<WorkOrderStage>>(
    `/work-orders/${workOrderId}/stages/${stageId}/close`,
  )
  return data.data
}

/** POST /work-orders/{id}/stages/{stage}/reopen: clears `closed_at`/`closed_by`. */
export async function reopenWorkOrderStage(workOrderId: number, stageId: number): Promise<WorkOrderStage> {
  const { data } = await apiClient.post<ApiResponse<WorkOrderStage>>(
    `/work-orders/${workOrderId}/stages/${stageId}/reopen`,
  )
  return data.data
}

/** POST /work-orders/{id}/task-board/move: `position` is the destination index AFTER removing the task from its origin group (AC-013/AC-014). */
export async function moveBoardTask(
  workOrderId: number,
  payload: MoveBoardTaskPayload,
): Promise<MoveBoardTaskResult> {
  const { data } = await apiClient.post<ApiResponse<MoveBoardTaskResult>>(
    `/work-orders/${workOrderId}/task-board/move`,
    payload,
  )
  return data.data
}

/** POST /work-orders/{id}/task-board/bulk: partial failure is expected — each task's own outcome lands in `results[]`, never a single all-or-nothing rejection (D-7). */
export async function bulkBoardTaskAction(
  workOrderId: number,
  payload: BulkTaskPayload,
): Promise<BulkTaskResult> {
  const { data } = await apiClient.post<ApiResponse<BulkTaskResult>>(
    `/work-orders/${workOrderId}/task-board/bulk`,
    payload,
  )
  return data.data
}
