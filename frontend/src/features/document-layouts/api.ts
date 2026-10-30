import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CloneDocumentLayoutPayload,
  CreateDocumentLayoutPayload,
  DocumentLayoutDetail,
  DocumentLayoutDetailWithPermissions,
  UpdateDocumentLayoutPayload,
} from '@/features/document-layouts/types'

/** Query key of a single document layout's detail, shared by the detail screen and its in-place saves. */
export function documentLayoutDetailQueryKey(id: number) {
  return ['document-layouts', 'detail', id] as const
}

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
 * Clones a document layout (spec 0339): a new layout of the same module with
 * the given name/code, the source's content and copies of its images.
 * Returns the created resource from the envelope `data`.
 */
export async function cloneDocumentLayout(
  id: number,
  payload: CloneDocumentLayoutPayload,
): Promise<DocumentLayoutDetail> {
  const { data } = await apiClient.post<ApiResponse<DocumentLayoutDetail>>(
    `/document-layouts/${id}/clone`,
    payload,
  )
  return data.data
}

/**
 * Partially updates a document layout (PATCH). `code`/`module` are never
 * keys of `UpdateDocumentLayoutPayload` (spec 0069 `validation`): the
 * backend rejects their mere presence with 422 regardless of role. Returns
 * the saved record with the actor's permissions, so the in-place detail
 * re-syncs on it as is.
 */
export async function updateDocumentLayout(
  id: number,
  payload: UpdateDocumentLayoutPayload,
): Promise<DocumentLayoutDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<DocumentLayoutDetail, ResourcePermissions>
  >(`/document-layouts/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
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
