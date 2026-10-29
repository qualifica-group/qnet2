import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { filenameFromContentDisposition } from '@/lib/download'
import type {
  AbsenceBalanceOverviewFilters,
  AbsenceBalanceOverviewPage,
  AbsenceBalanceOverviewParams,
  AbsenceBalanceRow,
  AbsenceBalances,
  AbsenceBalancesParams,
  AbsenceBulkDecisionPayload,
  AbsenceBulkDecisionResult,
  AbsenceCalendar,
  AbsenceCalendarParams,
  AbsenceRequest,
  AbsenceRequestInsight,
  AbsenceSummary,
  AbsenceTypeOption,
  AllowancePayload,
  BalanceExportFormat,
  BulkAllowancePayload,
  CreateAbsenceRequestPayload,
  PreviewAbsenceRequestPayload,
  RejectAbsenceRequestPayload,
  UpdateAbsenceRequestPayload,
} from '@/features/absences/types'

const REQUESTS = '/absence-requests'

async function postAction(id: number, action: string, body?: object): Promise<AbsenceRequest> {
  const { data } = await apiClient.post<ApiResponse<AbsenceRequest>>(`${REQUESTS}/${id}/${action}`, body)
  return data.data
}

export async function fetchAbsenceRequest(id: number): Promise<AbsenceRequest> {
  const { data } = await apiClient.get<ApiResponse<AbsenceRequest>>(`${REQUESTS}/${id}`)
  return data.data
}

export async function createAbsenceRequest(payload: CreateAbsenceRequestPayload): Promise<AbsenceRequest> {
  const { data } = await apiClient.post<ApiResponse<AbsenceRequest>>(REQUESTS, payload)
  return data.data
}

/** Draft only: the server answers 409 otherwise. */
export async function updateAbsenceRequest(
  id: number,
  payload: UpdateAbsenceRequestPayload,
): Promise<AbsenceRequest> {
  const { data } = await apiClient.patch<ApiResponse<AbsenceRequest>>(`${REQUESTS}/${id}`, payload)
  return data.data
}

export async function deleteAbsenceRequest(id: number): Promise<void> {
  await apiClient.delete(`${REQUESTS}/${id}`)
}

export const submitAbsenceRequest = (id: number) => postAction(id, 'submit')
export const approveAbsenceRequest = (id: number) => postAction(id, 'approve')
export const cancelAbsenceRequest = (id: number) => postAction(id, 'cancel')
export const rejectAbsenceRequest = (id: number, payload: RejectAbsenceRequestPayload) =>
  postAction(id, 'reject', payload)

/** Uploads (or replaces) the single attachment; no `Content-Type` is forced so axios adds the boundary. */
export async function uploadAbsenceAttachment(id: number, file: File): Promise<AbsenceRequest> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await apiClient.post<ApiResponse<AbsenceRequest>>(`${REQUESTS}/${id}/attachment`, formData)
  return data.data
}

export async function deleteAbsenceAttachment(id: number): Promise<void> {
  await apiClient.delete(`${REQUESTS}/${id}/attachment`)
}

export interface AbsenceAttachmentFile {
  blob: Blob
  filename: string | null
}

/** Authenticated download of the attachment (the endpoint streams the file). */
export async function downloadAbsenceAttachment(id: number): Promise<AbsenceAttachmentFile> {
  const response = await apiClient.get<Blob>(`${REQUESTS}/${id}/attachment`, { responseType: 'blob' })
  return {
    blob: response.data,
    filename: filenameFromContentDisposition(response.headers['content-disposition']),
  }
}

export async function fetchAbsenceBalances(params: AbsenceBalancesParams = {}): Promise<AbsenceBalances> {
  const { data } = await apiClient.get<ApiResponse<AbsenceBalances>>('/absence-balances', { params })
  return data.data
}

/** Sets the yearly allowance of a person for a deducting type; answers the updated balance row. */
export async function saveAbsenceAllowance(payload: AllowancePayload): Promise<AbsenceBalanceRow> {
  const { data } = await apiClient.put<ApiResponse<AbsenceBalanceRow>>('/absence-allowances', payload)
  return data.data
}

/** People x types balances for the HR office (`absence-requests.manageAll`), paginated. */
export async function fetchAbsenceBalanceOverview(
  params: AbsenceBalanceOverviewParams,
): Promise<AbsenceBalanceOverviewPage> {
  const { data } = await apiClient.get<AbsenceBalanceOverviewPage>('/absence-balances/overview', { params })
  return { data: data.data, meta: data.meta }
}

/** Sets (or resets to the type default) the allowance of many people at once; answers how many rows changed. */
export async function bulkSaveAbsenceAllowances(payload: BulkAllowancePayload): Promise<{ updated: number }> {
  const { data } = await apiClient.put<ApiResponse<{ updated: number }>>('/absence-allowances/bulk', payload)
  return data.data
}

/** Same filters as the overview, without paging; the server streams a CSV or XLSX file. */
export async function exportAbsenceBalanceOverview(
  filters: AbsenceBalanceOverviewFilters,
  format: BalanceExportFormat,
): Promise<AbsenceAttachmentFile> {
  const response = await apiClient.get<Blob>('/absence-balances/overview/export', {
    params: { ...filters, format },
    responseType: 'blob',
  })
  return {
    blob: response.data,
    filename: filenameFromContentDisposition(response.headers['content-disposition']),
  }
}

/** Active types for the request form (authentication only). */
export async function fetchAbsenceTypeOptions(): Promise<AbsenceTypeOption[]> {
  const { data } = await apiClient.get<ApiResponse<AbsenceTypeOption[]>>('/absence-types/for-select')
  return data.data
}

/** Who is absent day by day among the people the actor's calendar permission reveals (spec 0279). */
export async function fetchAbsenceCalendar(params: AbsenceCalendarParams): Promise<AbsenceCalendar> {
  const { data } = await apiClient.get<ApiResponse<AbsenceCalendar>>('/absences/calendar', { params })
  return data.data
}

/** Dry run of a request: days, minutes, balance after, notice, overlaps and absent colleagues. Writes nothing. */
export async function previewAbsenceRequest(payload: PreviewAbsenceRequestPayload): Promise<AbsenceRequestInsight> {
  const { data } = await apiClient.post<ApiResponse<AbsenceRequestInsight>>(`${REQUESTS}/preview`, payload)
  return data.data
}

/** Decision context of an existing request, colleagues according to the actor's visibility. */
export async function getAbsenceRequestInsight(id: number): Promise<AbsenceRequestInsight> {
  const { data } = await apiClient.get<ApiResponse<AbsenceRequestInsight>>(`${REQUESTS}/${id}/insight`)
  return data.data
}

/** Approves or rejects many requests; the ones that fail come back with the reason. */
export async function bulkDecideAbsenceRequests(
  payload: AbsenceBulkDecisionPayload,
): Promise<AbsenceBulkDecisionResult> {
  const { data } = await apiClient.post<ApiResponse<AbsenceBulkDecisionResult>>(`${REQUESTS}/bulk-decision`, payload)
  return data.data
}

/** Counters, next absences and who is out today (page summary, menu badge, dashboard). */
export async function getAbsenceSummary(): Promise<AbsenceSummary> {
  const { data } = await apiClient.get<ApiResponse<AbsenceSummary>>('/absences/summary')
  return data.data
}
