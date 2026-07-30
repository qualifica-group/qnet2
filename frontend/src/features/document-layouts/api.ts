import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateDocumentLayoutPayload,
  DocumentLayoutDetail,
  DocumentLayoutDetailWithPermissions,
  UpdateDocumentLayoutPayload,
} from '@/features/document-layouts/types'

/**
 * Fetches a single document layout detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchDocumentLayout(id: number): Promise<DocumentLayoutDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<DocumentLayoutDetail, ResourcePermissions>
  >(`/document-layouts/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a document layout. Returns the created resource from the envelope `data`. */
export async function createDocumentLayout(
  payload: CreateDocumentLayoutPayload,
): Promise<DocumentLayoutDetail> {
  const { data } = await apiClient.post<ApiResponse<DocumentLayoutDetail>>(
    '/document-layouts',
    payload,
  )
  return data.data
}

/**
 * Partially updates a document layout (PATCH). `code`/`module` are never
 * keys of `UpdateDocumentLayoutPayload` (spec 0069 `validation`): the
 * backend rejects their mere presence with 422 regardless of role. Returns
 * the updated resource.
 */
export async function updateDocumentLayout(
  id: number,
  payload: UpdateDocumentLayoutPayload,
): Promise<DocumentLayoutDetail> {
  const { data } = await apiClient.patch<ApiResponse<DocumentLayoutDetail>>(
    `/document-layouts/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a document layout. Backend responds 204 with no body. No
 * usage-based delete guard exists yet (spec 0070 D-10); the predefined-layout
 * guard (D-7) is enforced server-side and surfaces as a 422 the caller must
 * display.
 */
export async function deleteDocumentLayout(id: number): Promise<void> {
  await apiClient.delete(`/document-layouts/${id}`)
}
