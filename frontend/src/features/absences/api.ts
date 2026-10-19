import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { filenameFromContentDisposition, normalizeBlobError } from '@/lib/download'
import type {
  AbsenceBalanceRow,
  AbsenceBalances,
  AbsenceBalancesParams,
  AbsenceRequest,
  AbsenceTypeOption,
  AllowancePayload,
  CreateAbsenceRequestPayload,
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
  try {
    const response = await apiClient.get<Blob>(`${REQUESTS}/${id}/attachment`, { responseType: 'blob' })
    return {
      blob: response.data,
      filename: filenameFromContentDisposition(response.headers['content-disposition']),
    }
  } catch (error) {
    throw await normalizeBlobError(error)
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

/** Active types for the request form (authentication only). */
export async function fetchAbsenceTypeOptions(): Promise<AbsenceTypeOption[]> {
  const { data } = await apiClient.get<ApiResponse<AbsenceTypeOption[]>>('/absence-types/for-select')
  return data.data
}
