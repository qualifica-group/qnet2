import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import {
  CHART_ACCOUNTS_DOMAIN,
  REVENUE_TYPES_DOMAIN,
  type ChartAccountResource,
  type ChartTree,
  type CreateChartAccountPayload,
  type RevenueTypePayload,
  type RevenueTypeResource,
  type UpdateChartAccountPayload,
  type UpdateRevenueTypePayload,
} from '@/features/chart-of-accounts/types'

const ACCOUNTS_ENDPOINT = `/${CHART_ACCOUNTS_DOMAIN}`
const REVENUE_ENDPOINT = `/${REVENUE_TYPES_DOMAIN}`

export async function fetchChartTree(): Promise<ChartTree> {
  const { data } = await apiClient.get<ApiResponse<ChartTree>>('/chart-of-accounts/tree')
  return data.data
}

export async function createChartAccount(payload: CreateChartAccountPayload): Promise<ChartAccountResource> {
  const { data } = await apiClient.post<ApiResponse<ChartAccountResource>>(ACCOUNTS_ENDPOINT, payload)
  return data.data
}

export async function updateChartAccount(id: number, payload: UpdateChartAccountPayload): Promise<ChartAccountResource> {
  const { data } = await apiClient.patch<ApiResponse<ChartAccountResource>>(`${ACCOUNTS_ENDPOINT}/${id}`, payload)
  return data.data
}

/** 409 when the account still has children: the caller surfaces the backend message. */
export async function deleteChartAccount(id: number): Promise<void> {
  await apiClient.delete(`${ACCOUNTS_ENDPOINT}/${id}`)
}

export async function createRevenueType(payload: RevenueTypePayload): Promise<RevenueTypeResource> {
  const { data } = await apiClient.post<ApiResponse<RevenueTypeResource>>(REVENUE_ENDPOINT, payload)
  return data.data
}

export async function updateRevenueType(id: number, payload: UpdateRevenueTypePayload): Promise<RevenueTypeResource> {
  const { data } = await apiClient.patch<ApiResponse<RevenueTypeResource>>(`${REVENUE_ENDPOINT}/${id}`, payload)
  return data.data
}

export async function deleteRevenueType(id: number): Promise<void> {
  await apiClient.delete(`${REVENUE_ENDPOINT}/${id}`)
}
