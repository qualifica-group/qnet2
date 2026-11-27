import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  CreateDocumentRequestPayload,
  DocumentRequest,
  DocumentRequestDefaults,
  DocumentRequestList,
  DocumentRequestSyncLog,
  ReviewDocumentRequestItemPayload,
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

export type DocumentRequestAction = 'remind' | 'cancel' | 'refresh' | 'complete'

/** Queues a remind, cancel, refresh or complete of one request; the caller rereads the list, so the body is not used. */
export async function runDocumentRequestAction(id: number, action: DocumentRequestAction): Promise<void> {
  await apiClient.post(`/document-requests/${id}/${action}`)
}

/** Last outbound calls of a request, newest first. */
export async function fetchDocumentRequestSyncLogs(id: number): Promise<DocumentRequestSyncLog[]> {
  const { data } = await apiClient.get<ApiResponse<DocumentRequestSyncLog[]>>(`/document-requests/${id}/sync-logs`)
  return data.data
}

/** Queues the manual approval or rejection of one item; the note is optional to approve, mandatory to reject. */
export async function reviewDocumentRequestItem(
  id: number,
  { itemId, decision, note }: ReviewDocumentRequestItemPayload,
): Promise<void> {
  await apiClient.post(`/document-requests/${id}/items/${itemId}/${decision}`, { note })
}

/** Copies the current checklist rules into the request (spec 0432). */
export async function refreshDocumentRequestRules(id: number): Promise<void> {
  await apiClient.post(`/document-requests/${id}/refresh-rules`)
}

/** Validates the files of these items again with the AI: one call per item. */
export async function revalidateDocumentRequestItems(id: number, itemIds: number[]): Promise<void> {
  await apiClient.post(`/document-requests/${id}/revalidate`, { item_ids: itemIds })
}

/** Moves the files of an item still waiting for the AI to manual review. */
export async function stopDocumentRequestItemValidation(id: number, itemId: number | string): Promise<void> {
  await apiClient.post(`/document-requests/${id}/items/${itemId}/stop-validation`)
}

/** Draft, sent and in progress requests of a checklist: the ones a correction can reach. */
export async function fetchChecklistOpenRequestsCount(checklistId: number): Promise<number> {
  const { data } = await apiClient.get<ApiResponse<{ count: number }>>(`/document-checklists/${checklistId}/open-requests`)
  return data.data.count
}

/** Queues the refresh of the rules on every open request of the checklist; returns how many. */
export async function refreshChecklistOpenRequests(checklistId: number): Promise<number> {
  const { data } = await apiClient.post<ApiResponse<{ queued: number }>>(
    `/document-checklists/${checklistId}/refresh-open-requests`,
  )
  return data.data.queued
}

/** One file of an item, streamed by the backend through the authenticated client (the browser never sees the ArchivioSmart URL). */
export async function downloadDocumentRequestFile(id: number, documentId: number | string): Promise<Blob> {
  const { data } = await apiClient.get<Blob>(`/document-requests/${id}/documents/${documentId}/download`, {
    responseType: 'blob',
  })
  return data
}
