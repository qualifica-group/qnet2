import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { DocumentChecklistSyncLog } from '@/features/document-checklists/types'
import type {
  DocumentChecklistCategory,
  DocumentChecklistCategoryPayload,
  DocumentChecklistCategoryWithPermissions,
} from '@/features/document-checklist-categories/types'

/** Resource segment of the standard for-select endpoint (ADR 0011). */
export const DOCUMENT_CHECKLIST_CATEGORIES_FOR_SELECT_RESOURCE = 'document-checklist-categories'

const BASE_PATH = '/document-checklist-categories'

type CategoryEnvelope = ApiResponseWithPermissions<DocumentChecklistCategory, ResourcePermissions>

function withPermissions(envelope: CategoryEnvelope): DocumentChecklistCategoryWithPermissions {
  return { ...envelope.data, permissions: envelope.permissions }
}

/** Fetches a category with the actor's permissions. */
export async function fetchDocumentChecklistCategory(id: number): Promise<DocumentChecklistCategoryWithPermissions> {
  const { data } = await apiClient.get<CategoryEnvelope>(`${BASE_PATH}/${id}`)
  return withPermissions(data)
}

/** Creates a category; the sync to ArchivioSmart is queued by the backend. */
export async function createDocumentChecklistCategory(
  payload: DocumentChecklistCategoryPayload,
): Promise<DocumentChecklistCategoryWithPermissions> {
  const { data } = await apiClient.post<CategoryEnvelope>(BASE_PATH, payload)
  return withPermissions(data)
}

/** Replaces a category (full replacement). */
export async function updateDocumentChecklistCategory(
  id: number,
  payload: DocumentChecklistCategoryPayload,
): Promise<DocumentChecklistCategoryWithPermissions> {
  const { data } = await apiClient.put<CategoryEnvelope>(`${BASE_PATH}/${id}`, payload)
  return withPermissions(data)
}

/** Soft-deletes a category (204); 422 on `category` while it still has checklists. */
export async function removeDocumentChecklistCategory(id: number): Promise<void> {
  await apiClient.delete(`${BASE_PATH}/${id}`)
}

/** Queues a new sync of one category (202). */
export async function syncDocumentChecklistCategory(id: number): Promise<DocumentChecklistCategory> {
  const { data } = await apiClient.post<ApiResponse<DocumentChecklistCategory>>(`${BASE_PATH}/${id}/sync`)
  return data.data
}

/** Last outbound calls of a category, newest first. */
export async function fetchDocumentChecklistCategorySyncLogs(id: number): Promise<DocumentChecklistSyncLog[]> {
  const { data } = await apiClient.get<ApiResponse<DocumentChecklistSyncLog[]>>(`${BASE_PATH}/${id}/sync-logs`)
  return data.data
}
