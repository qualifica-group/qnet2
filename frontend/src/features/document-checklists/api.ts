import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  ArchivioSmartConnectionTest,
  ArchivioSmartSettings,
  DocumentChecklist,
  DocumentChecklistPayload,
  DocumentChecklistSyncLog,
  DocumentChecklistWithPermissions,
  UpdateArchivioSmartSettingsPayload,
} from '@/features/document-checklists/types'

type ChecklistEnvelope = ApiResponseWithPermissions<DocumentChecklist, ResourcePermissions>

function withPermissions(envelope: ChecklistEnvelope): DocumentChecklistWithPermissions {
  return { ...envelope.data, permissions: envelope.permissions }
}

/** Fetches a checklist with its entries and the actor's permissions. */
export async function fetchDocumentChecklist(id: number): Promise<DocumentChecklistWithPermissions> {
  const { data } = await apiClient.get<ChecklistEnvelope>(`/document-checklists/${id}`)
  return withPermissions(data)
}

/** Creates a checklist; the sync to ArchivioSmart is queued by the backend. */
export async function createDocumentChecklist(
  payload: DocumentChecklistPayload,
): Promise<DocumentChecklistWithPermissions> {
  const { data } = await apiClient.post<ChecklistEnvelope>('/document-checklists', payload)
  return withPermissions(data)
}

/** Replaces a checklist and its entries (full replacement). */
export async function updateDocumentChecklist(
  id: number,
  payload: DocumentChecklistPayload,
): Promise<DocumentChecklistWithPermissions> {
  const { data } = await apiClient.put<ChecklistEnvelope>(`/document-checklists/${id}`, payload)
  return withPermissions(data)
}

/** Activates or deactivates a checklist. */
export async function setDocumentChecklistActivation(
  id: number,
  isActive: boolean,
): Promise<DocumentChecklistWithPermissions> {
  const { data } = await apiClient.patch<ChecklistEnvelope>(`/document-checklists/${id}/activation`, {
    is_active: isActive,
  })
  return withPermissions(data)
}

/** Soft-deletes a checklist. Backend responds 204 with no body. */
export async function removeDocumentChecklist(id: number): Promise<void> {
  await apiClient.delete(`/document-checklists/${id}`)
}

/** Queues a new sync of one checklist (202). */
export async function syncDocumentChecklist(id: number): Promise<DocumentChecklist> {
  const { data } = await apiClient.post<ApiResponse<DocumentChecklist>>(`/document-checklists/${id}/sync`)
  return data.data
}

/** Queues a sync of every non-deleted checklist (202). */
export async function syncAllDocumentChecklists(): Promise<{ queued: number }> {
  const { data } = await apiClient.post<ApiResponse<{ queued: number }>>('/document-checklists/sync-all')
  return data.data
}

/** Last outbound calls of a checklist, newest first. */
export async function fetchDocumentChecklistSyncLogs(id: number): Promise<DocumentChecklistSyncLog[]> {
  const { data } = await apiClient.get<ApiResponse<DocumentChecklistSyncLog[]>>(
    `/document-checklists/${id}/sync-logs`,
  )
  return data.data
}

export async function getArchivioSmartSettings(): Promise<ArchivioSmartSettings> {
  const { data } = await apiClient.get<ApiResponse<ArchivioSmartSettings>>('/archivio-smart/settings')
  return data.data
}

/** Saves the connection. An empty token is omitted so the saved one stays unchanged. */
export async function updateArchivioSmartSettings(
  payload: UpdateArchivioSmartSettingsPayload,
): Promise<ArchivioSmartSettings> {
  const body: UpdateArchivioSmartSettingsPayload = { base_url: payload.base_url }
  if (payload.token) {
    body.token = payload.token
  }
  const { data } = await apiClient.put<ApiResponse<ArchivioSmartSettings>>('/archivio-smart/settings', body)
  return data.data
}

/** Tests the saved connection (GET /me on ArchivioSmart). */
export async function testArchivioSmartConnection(): Promise<ArchivioSmartConnectionTest> {
  const { data } = await apiClient.post<ApiResponse<ArchivioSmartConnectionTest>>('/archivio-smart/settings/test')
  return data.data
}
