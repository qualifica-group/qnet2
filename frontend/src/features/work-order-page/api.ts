import { isAxiosError, type AxiosError } from 'axios'
import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import {
  PAGE_VERSION_CONFLICT_CODE,
  type CreatePollPayload,
  type Poll,
  type SavePagePayload,
  type WorkOrderPagePayload,
} from '@/features/work-order-page/types'

const pagePath = (workOrderId: number) => `/work-orders/${workOrderId}/page`

export async function fetchWorkOrderPage(workOrderId: number): Promise<WorkOrderPagePayload> {
  const { data } = await apiClient.get<ApiResponse<WorkOrderPagePayload>>(pagePath(workOrderId))
  return data.data
}

export async function saveWorkOrderPage(workOrderId: number, payload: SavePagePayload): Promise<WorkOrderPagePayload> {
  const { data } = await apiClient.put<ApiResponse<WorkOrderPagePayload>>(pagePath(workOrderId), payload)
  return data.data
}

export async function createPagePoll(workOrderId: number, payload: CreatePollPayload): Promise<Poll> {
  const { data } = await apiClient.post<ApiResponse<Poll>>(`${pagePath(workOrderId)}/polls`, payload)
  return data.data
}

export async function votePagePoll(workOrderId: number, pollId: number, optionKey: string): Promise<Poll> {
  const { data } = await apiClient.put<ApiResponse<Poll>>(`${pagePath(workOrderId)}/polls/${pollId}/vote`, {
    option_key: optionKey,
  })
  return data.data
}

export async function retractPagePollVote(workOrderId: number, pollId: number): Promise<Poll> {
  const { data } = await apiClient.delete<ApiResponse<Poll>>(`${pagePath(workOrderId)}/polls/${pollId}/vote`)
  return data.data
}

interface PageErrorBody {
  code?: string
  data?: WorkOrderPagePayload
}

/** A 409 `page_version_conflict`: someone else saved first. */
export function isPageConflict(error: unknown): boolean {
  return isAxiosError<PageErrorBody>(error) && error.response?.status === 409 && error.response.data?.code === PAGE_VERSION_CONFLICT_CODE
}

/** The current server page carried by a conflict 409 (`null` when absent or for any other error). */
export function pageConflictPayload(error: unknown): WorkOrderPagePayload | null {
  return isPageConflict(error) ? ((error as AxiosError<PageErrorBody>).response?.data?.data ?? null) : null
}

/** A 409 that is not a version conflict: the work order is completed/closed, the page is read-only. */
export function isPageClosedError(error: unknown): boolean {
  return isAxiosError(error) && error.response?.status === 409 && !isPageConflict(error)
}
