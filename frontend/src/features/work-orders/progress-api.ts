import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import type { CreateProgressEntryPayload, WorkOrderProgressEntry } from '@/features/work-orders/types'

/** Query key of a work order's manual progress entries (spec 0321). */
export function workOrderProgressQueryKey(workOrderId: number) {
  return [WORK_ORDERS_DOMAIN, 'progress-entries', workOrderId] as const
}

/** Entries of a work order in insertion order; each `percentage` is that row's increment. */
export async function fetchProgressEntries(workOrderId: number): Promise<WorkOrderProgressEntry[]> {
  const { data } = await apiClient.get<ApiResponse<WorkOrderProgressEntry[]>>(
    `/work-orders/${workOrderId}/progress-entries`,
  )
  return data.data
}

/** Registers the new total percentage; 422 carries field errors. */
export async function createProgressEntry(
  workOrderId: number,
  payload: CreateProgressEntryPayload,
): Promise<WorkOrderProgressEntry> {
  const { data } = await apiClient.post<ApiResponse<WorkOrderProgressEntry>>(
    `/work-orders/${workOrderId}/progress-entries`,
    payload,
  )
  return data.data
}

export async function deleteProgressEntry(workOrderId: number, entryId: number): Promise<void> {
  await apiClient.delete(`/work-orders/${workOrderId}/progress-entries/${entryId}`)
}
