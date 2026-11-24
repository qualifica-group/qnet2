import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  CreateDocumentRequestPayload,
  DocumentRequest,
  DocumentRequestDefaults,
  DocumentRequestList,
  DocumentRequestSyncLog,
  UpdateDocumentRequestPayload,
} from '@/features/document-requests/types'

/** Resource segment of the checklist for-select used by the request dialog (active and synced only). */
export const DOCUMENT_CHECKLISTS_FOR_SELECT_RESOURCE = 'document-checklists'

interface DocumentRequestListEnvelope extends ApiResponse<DocumentRequest[]> {
  meta: { can_create: boolean }
}

export async function fetchDocumentRequests(workOrderId: number): Promise<DocumentRequestList> {
  const { data } = await apiClient.get<DocumentRequestListEnvelope>(`/work-orders/${workOrderId}/document-requests`)
  return { requests: data.data, canCreate: data.meta.can_create }
}

/** Pre-fill values of the request dialog (customer primary email). */
export async function fetchDocumentRequestDefaults(workOrderId: number): Promise<DocumentRequestDefaults> {
  const { data } = await apiClient.get<ApiResponse<DocumentRequestDefaults>>(
    `/work-orders/${workOrderId}/document-requests/defaults`,
  )
  return data.data
}

/** Creates the request; the call to ArchivioSmart is queued by the backend. */
export async function createDocumentRequest(
  workOrderId: number,
  payload: CreateDocumentRequestPayload,
): Promise<DocumentRequest> {
  const { data } = await apiClient.post<ApiResponse<DocumentRequest>>(
    `/work-orders/${workOrderId}/document-requests`,
    payload,
  )
  return data.data
}

export async function updateDocumentRequest(
  id: number,
  payload: UpdateDocumentRequestPayload,
): Promise<DocumentRequest> {
  const { data } = await apiClient.put<ApiResponse<DocumentRequest>>(`/document-requests/${id}`, payload)
  return data.data
}

export type DocumentRequestAction = 'remind' | 'cancel' | 'refresh'

/** Queues a remind, cancel or refresh of one request; the caller rereads the list, so the body is not used. */
export async function runDocumentRequestAction(id: number, action: DocumentRequestAction): Promise<void> {
  await apiClient.post(`/document-requests/${id}/${action}`)
}

/** Last outbound calls of a request, newest first. */
export async function fetchDocumentRequestSyncLogs(id: number): Promise<DocumentRequestSyncLog[]> {
  const { data } = await apiClient.get<ApiResponse<DocumentRequestSyncLog[]>>(`/document-requests/${id}/sync-logs`)
  return data.data
}
