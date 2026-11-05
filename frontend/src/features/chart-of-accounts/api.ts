import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { filenameFromContentDisposition } from '@/lib/download'
import {
  CHART_ACCOUNTS_DOMAIN,
  REVENUE_TYPES_DOMAIN,
  TREASURY_TYPES_DOMAIN,
  type ChartAccountResource,
  type ChartExportFormat,
  type ChartImportResult,
  type ChartTree,
  type CreateChartAccountPayload,
  type RevenueTypePayload,
  type RevenueTypeResource,
  type TreasuryTypePayload,
  type TreasuryTypeResource,
  type UpdateChartAccountPayload,
  type UpdateRevenueTypePayload,
  type UpdateTreasuryTypePayload,
} from '@/features/chart-of-accounts/types'

const ACCOUNTS_ENDPOINT = `/${CHART_ACCOUNTS_DOMAIN}`
const REVENUE_ENDPOINT = `/${REVENUE_TYPES_DOMAIN}`
const TREASURY_ENDPOINT = `/${TREASURY_TYPES_DOMAIN}`

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

export async function createTreasuryType(payload: TreasuryTypePayload): Promise<TreasuryTypeResource> {
  const { data } = await apiClient.post<ApiResponse<TreasuryTypeResource>>(TREASURY_ENDPOINT, payload)
  return data.data
}

export async function updateTreasuryType(id: number, payload: UpdateTreasuryTypePayload): Promise<TreasuryTypeResource> {
  const { data } = await apiClient.patch<ApiResponse<TreasuryTypeResource>>(`${TREASURY_ENDPOINT}/${id}`, payload)
  return data.data
}

export async function deleteTreasuryType(id: number): Promise<void> {
  await apiClient.delete(`${TREASURY_ENDPOINT}/${id}`)
}

/** Whole plan as a spreadsheet; the server answers a file download. */
export async function exportChartOfAccounts(
  format: ChartExportFormat,
): Promise<{ blob: Blob; filename: string | null }> {
  const response = await apiClient.get<Blob>('/chart-of-accounts/export', { params: { format }, responseType: 'blob' })
  return { blob: response.data, filename: filenameFromContentDisposition(response.headers['content-disposition']) }
}

/** `dryRun` previews counts and errors without writing; false applies all-or-nothing. */
export async function importChartOfAccounts(file: File, dryRun: boolean): Promise<ChartImportResult> {
  const body = new FormData()
  body.append('file', file)
  body.append('dry_run', dryRun ? '1' : '0')
  const { data } = await apiClient.post<ApiResponse<ChartImportResult>>('/chart-of-accounts/import', body)
  return data.data
}
